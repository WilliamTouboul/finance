<?php

declare(strict_types=1);

/**
 * Modele de configuration.
 *
 * Copier ce fichier en config/config.php et adapter les valeurs.
 * config/config.php n'est jamais versionne : il contient les identifiants.
 */
return [
    // 'dev' active l'affichage detaille des erreurs. 'prod' les masque.
    'env' => 'dev',

    // Nom affiche dans l'interface.
    'app_name' => 'Finance',

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'finance',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'finance_session',
        // Passer a true une fois le site servi en HTTPS.
        'secure'   => false,
        // Duree d'inactivite avant deconnexion, en secondes.
        'lifetime' => 7200,
    ],
];
