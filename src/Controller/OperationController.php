<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Csrf;
use App\Core\Money;
use App\Core\NotFoundException;
use App\Core\Period;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Operation;
use App\Repository\OperationRepository;
use App\Repository\TagRepository;
use App\Service\Auth;
use DateTimeImmutable;

/**
 * Saisie et consultation des operations.
 *
 * Le montant se saisit en valeur absolue, accompagne d'un sens (depense ou
 * recette). Un champ unique ou le signe porterait le sens serait plus court a
 * coder, mais oublier un signe moins transformerait une depense en recette
 * sans que rien ne le signale. Un signe negatif saisi malgre tout est accepte
 * et interprete comme une depense.
 */
final class OperationController extends BaseController
{
    private const MAX_LABEL_LENGTH = 150;
    private const MAX_NOTE_LENGTH  = 1000;

    private OperationRepository $operations;

    private TagRepository $tags;

    public function __construct(Auth $auth)
    {
        parent::__construct($auth);

        $this->operations = new OperationRepository();
        $this->tags       = new TagRepository();
    }

    public function index(Request $request): Response
    {
        $user   = $this->requireUser();
        $period = Period::fromParam($request->query('p'));

        $tagFilter = $this->resolveTagFilter($request, $user->id);

        return $this->view('operations/index', [
            'pageTitle'  => 'Opérations',
            'period'     => $period,
            'operations' => $this->operations->listForPeriod($user->id, $period, $tagFilter),
            'totals'     => $this->operations->totalsForPeriod($user->id, $period),
            'allTags'    => $this->tags->allForUser($user->id),
            'tagFilter'  => $tagFilter,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->view('operations/form', [
            'pageTitle' => 'Ajouter une opération',
            'operation' => null,
            'allTags'   => $this->tags->allForUser($user->id),
            'values'    => $this->defaultValues($request),
            'errors'    => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/operations/nouvelle');
        }

        $values = $this->readValues($request);
        $errors = $this->validate($values);

        if ($errors !== []) {
            return $this->view('operations/form', [
                'pageTitle' => 'Ajouter une opération',
                'operation' => null,
                'allTags'   => $this->tags->allForUser($user->id),
                'values'    => $values,
                'errors'    => $errors,
            ], 422);
        }

        $this->operations->create(
            $user->id,
            $values['label'],
            $this->signedAmount($values),
            new DateTimeImmutable($values['date']),
            $values['note'] === '' ? null : $values['note'],
            $this->tags->keepOwned($user->id, $values['tags']),
        );

        Session::flash('success', "« {$values['label']} » a été enregistré.");

        return $this->redirect('/operations?p=' . substr($values['date'], 0, 7));
    }

    public function edit(Request $request, string $id): Response
    {
        $user      = $this->requireUser();
        $operation = $this->operations->find((int) $id, $user->id);

        if ($operation === null) {
            throw new NotFoundException('Opération introuvable.');
        }

        return $this->view('operations/form', [
            'pageTitle' => 'Modifier une opération',
            'operation' => $operation,
            'allTags'   => $this->tags->allForUser($user->id),
            'values'    => $this->valuesFrom($operation),
            'errors'    => [],
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $user      = $this->requireUser();
        $operation = $this->operations->find((int) $id, $user->id);

        if ($operation === null) {
            throw new NotFoundException('Opération introuvable.');
        }

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/operations');
        }

        $values = $this->readValues($request);
        $errors = $this->validate($values);

        if ($errors !== []) {
            return $this->view('operations/form', [
                'pageTitle' => 'Modifier une opération',
                'operation' => $operation,
                'allTags'   => $this->tags->allForUser($user->id),
                'values'    => $values,
                'errors'    => $errors,
            ], 422);
        }

        $this->operations->update(
            $operation->id,
            $user->id,
            $values['label'],
            $this->signedAmount($values),
            new DateTimeImmutable($values['date']),
            $values['note'] === '' ? null : $values['note'],
            $this->tags->keepOwned($user->id, $values['tags']),
        );

        Session::flash('success', 'Opération mise à jour.');

        return $this->redirect('/operations?p=' . substr($values['date'], 0, 7));
    }

    public function delete(Request $request, string $id): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/operations');
        }

        $operation = $this->operations->find((int) $id, $user->id);

        if ($operation === null) {
            throw new NotFoundException('Opération introuvable.');
        }

        $this->operations->delete($operation->id, $user->id);
        Session::flash('info', "« {$operation->label} » a été supprimé.");

        return $this->redirect('/operations?p=' . $operation->occurredOn->format('Y-m'));
    }

    /**
     * Identifiant du tag servant de filtre, ou null.
     *
     * Verifie que le tag appartient bien a l'utilisateur : sinon un numero
     * saisi dans l'URL revelerait, par le simple fait de filtrer, l'existence
     * du tag d'un autre compte.
     */
    private function resolveTagFilter(Request $request, int $userId): ?int
    {
        $raw = $request->query('tag');

        if ($raw === null || !ctype_digit($raw)) {
            return null;
        }

        return $this->tags->find((int) $raw, $userId)?->id;
    }

    /**
     * @return array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>}
     */
    private function readValues(Request $request): array
    {
        return [
            'label'     => (string) $request->input('label', ''),
            'amount'    => (string) $request->input('amount', ''),
            'direction' => $request->input('direction') === 'income' ? 'income' : 'expense',
            'date'      => (string) $request->input('date', ''),
            'note'      => (string) $request->input('note', ''),
            'tags'      => array_map('intval', $request->inputArray('tags')),
        ];
    }

    /**
     * @return array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>}
     */
    private function defaultValues(Request $request): array
    {
        // La date propose par defaut le jour courant, ou le dernier jour de la
        // periode consultee si l'on saisit depuis un mois passe.
        $period = Period::fromParam($request->query('p'));
        $today  = new DateTimeImmutable();

        return [
            'label'     => '',
            'amount'    => '',
            'direction' => 'expense',
            'date'      => $period->isCurrent() ? $today->format('Y-m-d') : $period->endSql(),
            'note'      => '',
            'tags'      => [],
        ];
    }

    /**
     * @return array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>}
     */
    private function valuesFrom(Operation $operation): array
    {
        return [
            'label'     => $operation->label,
            'amount'    => Money::toInput(abs($operation->amountCents)),
            'direction' => $operation->isIncome() ? 'income' : 'expense',
            'date'      => $operation->occurredOn->format('Y-m-d'),
            'note'      => $operation->note ?? '',
            'tags'      => $operation->tagIds(),
        ];
    }

    /**
     * Applique le sens choisi au montant saisi.
     *
     * @param array{amount: string, direction: string} $values
     */
    private function signedAmount(array $values): int
    {
        // Le montant a deja ete valide : parse() ne peut plus echouer ici.
        $cents = abs((int) Money::parse($values['amount']));

        return $values['direction'] === 'income' ? $cents : -$cents;
    }

    /**
     * @param array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>} $values
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

        if (!$this->isValidDate($values['date'])) {
            $errors[] = 'Date invalide.';
        }

        if (mb_strlen($values['note']) > self::MAX_NOTE_LENGTH) {
            $errors[] = 'La note ne peut pas dépasser ' . self::MAX_NOTE_LENGTH . ' caractères.';
        }

        return $errors;
    }

    /**
     * Date reellement existante, au format attendu.
     *
     * La comparaison avec la chaine d'origine est necessaire : PHP accepte le
     * 31 fevrier et le reporte au 3 mars sans rien signaler.
     */
    private function isValidDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
