<?php

declare(strict_types=1);

/**
 * Change le mot de passe d un compte existant.
 *
 * Usage : php bin/reset-password.php
 *
 * L'application ne propose pas de procedure de mot de passe oublie : elle
 * supposerait l'envoi d'e-mails et une gestion de jetons de reinitialisation,
 * soit une surface d'attaque supplementaire pour un seul utilisateur qui a de
 * toute facon acces au serveur. Ce script tient ce role.
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

Prompt::line('Changement de mot de passe');
Prompt::line('--------------------------');

$user = null;
while ($user === null) {
    $user = $users->findByEmail(Prompt::ask('Adresse e-mail du compte'));

    if ($user === null) {
        Prompt::error('Aucun compte avec cette adresse.');
    }
}

$password = '';
while ($password === '') {
    $first = Prompt::askHidden('Nouveau mot de passe (' . MIN_PASSWORD_LENGTH . ' caracteres minimum)');

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

$users->updatePasswordHash($user->id, PasswordHasher::hash($password));

$password = str_repeat('0', 64);
unset($password);

Prompt::line();
Prompt::success("Mot de passe mis a jour pour {$user->email}.");
