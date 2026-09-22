<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Csrf;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Tag;
use App\Repository\TagRepository;
use App\Service\Auth;

/**
 * Gestion des poles de depense.
 *
 * Les tags se creent librement depuis cette page, puis s'associent aux
 * operations. Un meme tag peut servir a autant d'operations que voulu, et une
 * operation peut en porter plusieurs.
 */
final class TagController extends BaseController
{
    private const MAX_NAME_LENGTH = 50;

    private TagRepository $tags;

    public function __construct(Auth $auth)
    {
        parent::__construct($auth);

        $this->tags = new TagRepository();
    }

    public function index(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->view('tags/index', [
            'pageTitle' => 'Tags',
            'tags'      => $this->tags->allWithUsage($user->id),
            'formName'  => '',
            'formColor' => Tag::DEFAULT_COLOR,
            'errors'    => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/tags');
        }

        $name   = (string) $request->input('name', '');
        $color  = (string) $request->input('color', Tag::DEFAULT_COLOR);
        $errors = $this->validate($user->id, $name, $color);

        if ($errors !== []) {
            return $this->view('tags/index', [
                'pageTitle' => 'Tags',
                'tags'      => $this->tags->allWithUsage($user->id),
                'formName'  => $name,
                'formColor' => $color,
                'errors'    => $errors,
            ], 422);
        }

        $this->tags->create($user->id, $name, $color);
        Session::flash('success', "Le tag « {$name} » a été créé.");

        return $this->redirect('/tags');
    }

    public function edit(Request $request, string $id): Response
    {
        $user = $this->requireUser();
        $tag  = $this->tags->find((int) $id, $user->id);

        if ($tag === null) {
            throw new NotFoundException('Tag introuvable.');
        }

        return $this->view('tags/edit', [
            'pageTitle' => 'Modifier un tag',
            'tag'       => $tag,
            'formName'  => $tag->name,
            'formColor' => $tag->color,
            'errors'    => [],
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $user = $this->requireUser();
        $tag  = $this->tags->find((int) $id, $user->id);

        if ($tag === null) {
            throw new NotFoundException('Tag introuvable.');
        }

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/tags');
        }

        $name   = (string) $request->input('name', '');
        $color  = (string) $request->input('color', Tag::DEFAULT_COLOR);
        $errors = $this->validate($user->id, $name, $color, $tag->id);

        if ($errors !== []) {
            return $this->view('tags/edit', [
                'pageTitle' => 'Modifier un tag',
                'tag'       => $tag,
                'formName'  => $name,
                'formColor' => $color,
                'errors'    => $errors,
            ], 422);
        }

        $this->tags->update($tag->id, $user->id, $name, $color);
        Session::flash('success', 'Tag mis à jour.');

        return $this->redirect('/tags');
    }

    public function delete(Request $request, string $id): Response
    {
        $user = $this->requireUser();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/tags');
        }

        $tag = $this->tags->find((int) $id, $user->id);

        if ($tag === null) {
            throw new NotFoundException('Tag introuvable.');
        }

        $this->tags->delete($tag->id, $user->id);

        // Le message le precise : la suppression d'un pole ne doit pas laisser
        // croire que les depenses correspondantes ont disparu.
        Session::flash('info', "Le tag « {$tag->name} » a été supprimé. Les opérations concernées sont conservées.");

        return $this->redirect('/tags');
    }

    /**
     * @return array<int, string>
     */
    private function validate(int $userId, string $name, string $color, ?int $excludeId = null): array
    {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors[] = 'Le nom ne peut pas dépasser ' . self::MAX_NAME_LENGTH . ' caractères.';
        } elseif ($this->tags->nameExists($userId, $name, $excludeId)) {
            $errors[] = "Un tag nommé « {$name} » existe déjà.";
        }

        // La couleur vient d'un champ <input type="color">, mais rien
        // n'empeche de poster autre chose : on la valide comme le reste.
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            $errors[] = 'Couleur invalide.';
        }

        return $errors;
    }
}
