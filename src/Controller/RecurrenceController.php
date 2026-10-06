<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Csrf;
use App\Core\Frequency;
use App\Core\Money;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Recurrence;
use App\Repository\OperationRepository;
use App\Repository\RecurrenceRepository;
use App\Repository\TagRepository;
use App\Service\Auth;
use DateTimeImmutable;

/**
 * Operations recurrentes : les modeles, et la validation de leurs echeances.
 *
 * Une recurrence ne cree jamais d'operation d'elle-meme. Quand une echeance
 * est passee, elle rejoint une file d'attente que l'utilisateur confirme,
 * avec la possibilite d'ajuster le montant -- une facture d'electricite n'est
 * pas la meme tous les mois. C'est voulu : l'outil sert a pointer ses comptes,
 * pas a les remplir tout seul.
 */
final class RecurrenceController extends BaseController
{
    private const MAX_LABEL_LENGTH = 150;
    private const MAX_NOTE_LENGTH  = 1000;

    private RecurrenceRepository $recurrences;

    private OperationRepository $operations;

    private TagRepository $tags;

    public function __construct(Auth $auth)
    {
        parent::__construct($auth);

        $this->recurrences = new RecurrenceRepository();
        $this->operations  = new OperationRepository();
        $this->tags        = new TagRepository();
    }

    public function index(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->view('recurrences/index', [
            'pageTitle'   => 'Opérations récurrentes',
            'recurrences' => $this->recurrences->allForUser($user->id),
            'pending'     => $this->recurrences->pendingOccurrences($user->id),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->view('recurrences/form', [
            'pageTitle'  => 'Nouvelle récurrence',
            'recurrence' => null,
            'allTags'    => $this->tags->allForUser($user->id),
            'values'     => $this->defaultValues(),
            'errors'     => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/recurrences/nouvelle');
        }

        $values = $this->readValues($request);
        $errors = $this->validate($values);

        if ($errors !== []) {
            return $this->view('recurrences/form', [
                'pageTitle'  => 'Nouvelle récurrence',
                'recurrence' => null,
                'allTags'    => $this->tags->allForUser($user->id),
                'values'     => $values,
                'errors'     => $errors,
            ], 422);
        }

        $this->recurrences->create(
            $user->id,
            $values['label'],
            $this->signedAmount($values),
            Frequency::from($values['frequency']),
            new DateTimeImmutable($values['startsOn']),
            $values['endsOn'] === '' ? null : new DateTimeImmutable($values['endsOn']),
            $values['note'] === '' ? null : $values['note'],
            $this->tags->keepOwned($user->id, $values['tags']),
            $values['primaryTag'],
        );

        Session::flash('success', "La récurrence « {$values['label']} » a été créée.");

        return $this->redirect('/recurrences');
    }

    public function edit(Request $request, string $id): Response
    {
        $user       = $this->requireUser();
        $recurrence = $this->recurrences->find((int) $id, $user->id);

        if ($recurrence === null) {
            throw new NotFoundException('Récurrence introuvable.');
        }

        return $this->view('recurrences/form', [
            'pageTitle'  => 'Modifier une récurrence',
            'recurrence' => $recurrence,
            'allTags'    => $this->tags->allForUser($user->id),
            'values'     => $this->valuesFrom($recurrence),
            'errors'     => [],
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $user       = $this->requireUser();
        $recurrence = $this->recurrences->find((int) $id, $user->id);

        if ($recurrence === null) {
            throw new NotFoundException('Récurrence introuvable.');
        }

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/recurrences');
        }

        $values = $this->readValues($request);
        $errors = $this->validate($values);

        if ($errors !== []) {
            return $this->view('recurrences/form', [
                'pageTitle'  => 'Modifier une récurrence',
                'recurrence' => $recurrence,
                'allTags'    => $this->tags->allForUser($user->id),
                'values'     => $values,
                'errors'     => $errors,
            ], 422);
        }

        $this->recurrences->update(
            $recurrence->id,
            $user->id,
            $values['label'],
            $this->signedAmount($values),
            Frequency::from($values['frequency']),
            new DateTimeImmutable($values['startsOn']),
            $values['endsOn'] === '' ? null : new DateTimeImmutable($values['endsOn']),
            $values['note'] === '' ? null : $values['note'],
            $this->tags->keepOwned($user->id, $values['tags']),
            $values['primaryTag'],
        );

        // Les operations deja generees ne sont pas touchees : elles ont eu lieu
        // avec le montant d'alors, et reecrire l'historique serait faux.
        Session::flash('success', 'Récurrence mise à jour. Les opérations déjà enregistrées sont inchangées.');

        return $this->redirect('/recurrences');
    }

    public function toggle(Request $request, string $id): Response
    {
        $user       = $this->requireUser();
        $recurrence = $this->recurrences->find((int) $id, $user->id);

        if ($recurrence === null || !Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/recurrences');
        }

        $this->recurrences->setActive($recurrence->id, $user->id, !$recurrence->isActive);

        Session::flash('info', $recurrence->isActive
            ? "« {$recurrence->label} » est suspendue : plus aucune échéance ne sera proposée."
            : "« {$recurrence->label} » est réactivée.");

        return $this->redirect('/recurrences');
    }

    public function delete(Request $request, string $id): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/recurrences');
        }

        $recurrence = $this->recurrences->find((int) $id, $user->id);

        if ($recurrence === null) {
            throw new NotFoundException('Récurrence introuvable.');
        }

        $this->recurrences->delete($recurrence->id, $user->id);

        Session::flash('info', "« {$recurrence->label} » a été supprimée. Les opérations déjà enregistrées sont conservées.");

        return $this->redirect('/recurrences');
    }

    /**
     * Transforme une echeance en operation reelle.
     */
    public function confirm(Request $request): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/recurrences');
        }

        $recurrenceId = (int) $request->input('recurrence_id', '0');
        $date         = (string) $request->input('date', '');
        $amountInput  = (string) $request->input('amount', '');

        $match = $this->findPending($user->id, $recurrenceId, $date);

        if ($match === null) {
            // L'echeance n'est pas due, ou appartient a quelqu'un d'autre, ou a
            // deja ete validee dans un autre onglet. On ne cree rien : ce
            // chemin ne doit pas servir a inventer une operation a une date
            // arbitraire.
            Session::flash('info', 'Cette échéance n\'est plus en attente.');

            return $this->redirect('/recurrences');
        }

        $recurrence = $match['recurrence'];

        // Le montant peut etre ajuste a la validation : une facture varie.
        // A defaut de saisie valide, celui du modele s'applique.
        $cents = $amountInput === '' ? null : Money::parse($amountInput);
        $amount = $cents === null || $cents === 0
            ? $recurrence->amountCents
            : ($recurrence->isExpense() ? -abs($cents) : abs($cents));

        $this->operations->create(
            $user->id,
            $recurrence->label,
            $amount,
            $match['date'],
            $recurrence->note,
            $recurrence->tagIds(),
            $recurrence->primaryTagId,
            $recurrence->id,
        );

        Session::flash('success', "« {$recurrence->label} » du "
            . $match['date']->format('d/m/Y') . ' a été enregistré.');

        return $this->redirect('/recurrences');
    }

    /**
     * Valide toutes les echeances en attente, au montant prevu.
     */
    public function confirmAll(Request $request): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/recurrences');
        }

        $pending = $this->recurrences->pendingOccurrences($user->id);

        foreach ($pending as $item) {
            $this->operations->create(
                $user->id,
                $item['recurrence']->label,
                $item['recurrence']->amountCents,
                $item['date'],
                $item['recurrence']->note,
                $item['recurrence']->tagIds(),
                $item['recurrence']->primaryTagId,
                $item['recurrence']->id,
            );
        }

        $count = count($pending);

        Session::flash('success', $count === 0
            ? 'Aucune échéance en attente.'
            : "{$count} opération" . ($count > 1 ? 's' : '') . ' enregistrée' . ($count > 1 ? 's' : '') . '.');

        return $this->redirect('/recurrences');
    }

    /**
     * Retrouve une echeance dans la file d'attente.
     *
     * Passer par la file plutot que de faire confiance aux champs du
     * formulaire garantit que la date demandee correspond bien a une echeance
     * reelle de cette recurrence, et que celle-ci appartient a l'utilisateur.
     *
     * @return array{recurrence: Recurrence, date: DateTimeImmutable}|null
     */
    private function findPending(int $userId, int $recurrenceId, string $date): ?array
    {
        foreach ($this->recurrences->pendingOccurrences($userId) as $item) {
            if ($item['recurrence']->id === $recurrenceId && $item['date']->format('Y-m-d') === $date) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{label: string, amount: string, direction: string, frequency: string, startsOn: string, endsOn: string, note: string, tags: array<int, int>, primaryTag: int|null}
     */
    private function readValues(Request $request): array
    {
        $primary   = $request->input('primary_tag');
        $frequency = Frequency::tryFromString($request->input('frequency'));

        return [
            'label'      => (string) $request->input('label', ''),
            'amount'     => (string) $request->input('amount', ''),
            'direction'  => $request->input('direction') === 'income' ? 'income' : 'expense',
            'frequency'  => ($frequency ?? Frequency::Monthly)->value,
            'startsOn'   => (string) $request->input('starts_on', ''),
            'endsOn'     => (string) $request->input('ends_on', ''),
            'note'       => (string) $request->input('note', ''),
            'tags'       => array_map('intval', $request->inputArray('tags')),
            'primaryTag' => $primary !== null && ctype_digit($primary) ? (int) $primary : null,
        ];
    }

    /**
     * @return array{label: string, amount: string, direction: string, frequency: string, startsOn: string, endsOn: string, note: string, tags: array<int, int>, primaryTag: int|null}
     */
    private function defaultValues(): array
    {
        return [
            'label'      => '',
            'amount'     => '',
            'direction'  => 'expense',
            'frequency'  => Frequency::Monthly->value,
            'startsOn'   => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'endsOn'     => '',
            'note'       => '',
            'tags'       => [],
            'primaryTag' => null,
        ];
    }

    /**
     * @return array{label: string, amount: string, direction: string, frequency: string, startsOn: string, endsOn: string, note: string, tags: array<int, int>, primaryTag: int|null}
     */
    private function valuesFrom(Recurrence $recurrence): array
    {
        return [
            'label'      => $recurrence->label,
            'amount'     => Money::toInput(abs($recurrence->amountCents)),
            'direction'  => $recurrence->isExpense() ? 'expense' : 'income',
            'frequency'  => $recurrence->frequency->value,
            'startsOn'   => $recurrence->startsOn->format('Y-m-d'),
            'endsOn'     => $recurrence->endsOn?->format('Y-m-d') ?? '',
            'note'       => $recurrence->note ?? '',
            'tags'       => $recurrence->tagIds(),
            'primaryTag' => $recurrence->primaryTagId,
        ];
    }

    /**
     * @param array{amount: string, direction: string} $values
     */
    private function signedAmount(array $values): int
    {
        $cents = abs((int) Money::parse($values['amount']));

        return $values['direction'] === 'income' ? $cents : -$cents;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<int, string>
     */
    private function validate(array $values): array
    {
        $errors = [];

        if ($values['label'] === '') {
            $errors[] = 'Le libellé est obligatoire.';
        } elseif (mb_strlen($values['label']) > self::MAX_LABEL_LENGTH) {
            $errors[] = 'Le libellé ne peut pas dépasser ' . self::MAX_LABEL_LENGTH . ' caractères.';
        }

        $cents = Money::parse($values['amount']);

        if ($values['amount'] === '') {
            $errors[] = 'Le montant est obligatoire.';
        } elseif ($cents === null) {
            $errors[] = 'Montant invalide. Exemples acceptés : 42 · 42,50 · 1 234,56';
        } elseif ($cents === 0) {
            $errors[] = 'Le montant ne peut pas être nul.';
        }

        if (!self::isValidDate($values['startsOn'])) {
            $errors[] = 'La date de première échéance est invalide.';
        }

        if ($values['endsOn'] !== '') {
            if (!self::isValidDate($values['endsOn'])) {
                $errors[] = 'La date de fin est invalide.';
            } elseif ($values['endsOn'] < $values['startsOn']) {
                $errors[] = 'La date de fin ne peut pas précéder la première échéance.';
            }
        }

        if (mb_strlen($values['note']) > self::MAX_NOTE_LENGTH) {
            $errors[] = 'La note ne peut pas dépasser ' . self::MAX_NOTE_LENGTH . ' caractères.';
        }

        return $errors;
    }

    private static function isValidDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
