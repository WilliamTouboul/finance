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
use App\Model\OperationFilter;
use App\Repository\OperationRepository;
use App\Repository\TagRepository;
use App\Service\Auth;
use DateTimeImmutable;

/**
 * Saisie, consultation et export des operations.
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
        $filter = $this->buildFilter($request, $user->id);

        return $this->view('operations/index', [
            'pageTitle'  => 'Opérations',
            'filter'     => $filter,
            'period'     => $filter->period,
            'operations' => $this->operations->findBy($user->id, $filter),
            'totals'     => $this->operations->totalsFor($user->id, $filter),
            'allTags'    => $this->tags->allForUser($user->id),
        ]);
    }

    /**
     * Export CSV de ce qui est affiche, filtres compris.
     *
     * La reponse est construite en memoire plutot que diffusee au fil de l'eau :
     * un export de finances personnelles reste de l'ordre de quelques milliers
     * de lignes, et une reponse complete permet d'annoncer sa taille.
     */
    public function export(Request $request): Response
    {
        $user   = $this->requireUser();
        $filter = $this->buildFilter($request, $user->id);

        $rows = [['Date', 'Libellé', 'Montant', 'Pôle principal', 'Tags', 'Note']];

        foreach ($this->operations->findBy($user->id, $filter) as $operation) {
            $rows[] = [
                $operation->occurredOn->format('d/m/Y'),
                $operation->label,
                // Virgule decimale : c'est ce qu'attend un tableur configure en francais.
                Money::format($operation->amountCents, false),
                $operation->primaryTag()?->name ?? '',
                implode(', ', array_map(static fn ($t): string => $t->name, $operation->tags)),
                $operation->note ?? '',
            ];
        }

        $filename = 'operations-' . $filter->period->toParam() . '.csv';

        return Response::csv(self::toCsv($rows), $filename);
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

        $tagIds = $this->tags->keepOwned($user->id, $values['tags']);

        $this->operations->create(
            $user->id,
            $values['label'],
            $this->signedAmount($values),
            new DateTimeImmutable($values['date']),
            $values['note'] === '' ? null : $values['note'],
            $tagIds,
            $values['primaryTag'],
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

        $tagIds = $this->tags->keepOwned($user->id, $values['tags']);

        $this->operations->update(
            $operation->id,
            $user->id,
            $values['label'],
            $this->signedAmount($values),
            new DateTimeImmutable($values['date']),
            $values['note'] === '' ? null : $values['note'],
            $tagIds,
            $values['primaryTag'],
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
     * Assemble les criteres en ne retenant que des tags appartenant a l'utilisateur.
     *
     * Les identifiants viennent de l'URL : un numero quelconque y filtrerait
     * sinon sur le tag d'un autre compte, et la simple presence ou absence de
     * resultats en revelerait l'existence.
     */
    private function buildFilter(Request $request, int $userId): OperationFilter
    {
        $requested = array_map('intval', $request->queryArray('tags'));

        return OperationFilter::fromRequest($request, $this->tags->keepOwned($userId, $requested));
    }

    /**
     * Convertit un tableau de lignes en CSV.
     *
     * Deux choix imposes par Excel, qui reste l'outil le plus probable en face :
     * le point-virgule comme separateur, et une marque d'ordre des octets en
     * tete de fichier. Sans elle, Excel lit le fichier dans l'encodage du
     * systeme et transforme tous les accents en charabia.
     *
     * @param array<int, array<int, string>> $rows
     */
    private static function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\u{FEFF}" . $csv;
    }

    /**
     * @return array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>, primaryTag: int|null}
     */
    private function readValues(Request $request): array
    {
        $primary = $request->input('primary_tag');

        return [
            'label'      => (string) $request->input('label', ''),
            'amount'     => (string) $request->input('amount', ''),
            'direction'  => $request->input('direction') === 'income' ? 'income' : 'expense',
            'date'       => (string) $request->input('date', ''),
            'note'       => (string) $request->input('note', ''),
            'tags'       => array_map('intval', $request->inputArray('tags')),
            'primaryTag' => $primary !== null && ctype_digit($primary) ? (int) $primary : null,
        ];
    }

    /**
     * @return array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>, primaryTag: int|null}
     */
    private function defaultValues(Request $request): array
    {
        // La date propose par defaut le jour courant, ou le dernier jour de la
        // periode consultee si l'on saisit depuis un mois passe.
        $period = Period::fromParam($request->query('p'));
        $today  = new DateTimeImmutable();

        return [
            'label'      => '',
            'amount'     => '',
            'direction'  => 'expense',
            'date'       => $period->isCurrent() ? $today->format('Y-m-d') : $period->endSql(),
            'note'       => '',
            'tags'       => [],
            'primaryTag' => null,
        ];
    }

    /**
     * @return array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>, primaryTag: int|null}
     */
    private function valuesFrom(Operation $operation): array
    {
        return [
            'label'      => $operation->label,
            'amount'     => Money::toInput(abs($operation->amountCents)),
            'direction'  => $operation->isIncome() ? 'income' : 'expense',
            'date'       => $operation->occurredOn->format('Y-m-d'),
            'note'       => $operation->note ?? '',
            'tags'       => $operation->tagIds(),
            'primaryTag' => $operation->primaryTagId,
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
