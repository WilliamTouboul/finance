<?php

declare(strict_types=1);

/**
 * Cree un compte utilisateur.
 *
 * Usage : php bin/create-user.php
 *
 * Ce script est le SEUL moyen de creer un compte : l'application n'expose
 * aucune page d'inscription. Sur un dashboard de finances personnelles, une
 * inscription ouverte serait une surface d'attaque sans contrepartie -- il n'y
 * a qu'un utilisateur, et il a acces au serveur.
 *
 * Le mot de passe n'est jamais accepte en argument de ligne de commande : il
 * apparaitrait dans l'historique du shell et dans la liste des processus.
 */

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\ConsolePrompt as Prompt;
use App\Core\Database;
use App\Core\PasswordHasher;
use App\Repository\UserRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute uniquement en ligne de commande.\n");
}

const MIN_PASSWORD_LENGTH = 12;

$root = dirname(__DIR__);

require $root . '/src/Core/Autoloader.php';
Autoloader::register($root . '/src');

try {
    Config::load($root . '/config/config.php');
    Database::pdo();
} catch (Throwable $e) {
    Prompt::error($e->getMessage());
    exit(1);
}

$users = new UserRepository();

Prompt::line('Creation d un compte');
Prompt::line('---------------------');

$email = '';
while ($email === '') {
    $input = mb_strtolower(Prompt::ask('Adresse e-mail'));

    if (filter_var($input, FILTER_VALIDATE_EMAIL) === false) {
        Prompt::error('Adresse e-mail invalide.');
        continue;
    }

    if ($users->emailExists($input)) {
        Prompt::error('Un compte existe deja avec cette adresse.');
        continue;
    }

    $email = $input;
}

$displayName = Prompt::ask('Nom affiche');

$password = '';
while ($password === '') {
    $first = Prompt::askHidden('Mot de passe (' . MIN_PASSWORD_LENGTH . ' caracteres minimum)');

    if (mb_strlen($first) < MIN_PASSWORD_LENGTH) {
        Prompt::error('Trop court : ' . MIN_PASSWORD_LENGTH . ' caracteres minimum.');
        continue;
    }

    $second = Prompt::askHidden('Confirmation');

    if (!hash_equals($first, $second)) {
        Prompt::error('Les deux saisies different.');
        continue;
    }

    $password = $first;
}

try {
    $id = $users->create($email, $displayName, PasswordHasher::hash($password));
} catch (Throwable $e) {
    Prompt::error('Creation impossible : ' . $e->getMessage());
    exit(1);
}

// Effacement defensif : la variable ne doit pas trainer en memoire plus
// longtemps que necessaire.
$password = str_repeat('0', 64);
unset($password);

Prompt::line();
Prompt::success("Compte #{$id} cree pour {$email}.");
Prompt::line('Algorithme de hachage utilise : ' . PasswordHasher::algorithmName());
Prompt::line('Connectez-vous sur /connexion');
