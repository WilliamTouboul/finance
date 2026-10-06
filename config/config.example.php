<?php

declare(strict_types=1);

/**
 * Modele de configuration.
 *
 * Copier ce fichier en config/config.php et adapter les valeurs.
 * config/config.php n'est jamais versionne : il contient les identifiants.
 *
 * Avant d'ouvrir l'acces depuis Internet, lancer php bin/check-prod.php :
 * il verifie les points ci-dessous et refuse de valider tant qu'il en reste un.
 */
return [
    /*
     * 'dev'  : les traces d'erreur s'affichent dans le navigateur.
     * 'prod' : elles sont uniquement journalisees dans var/log/php-error.log.
     *
     * Une trace affichee en production revele l'arborescence du serveur, les
     * versions installees et parfois le contenu des variables.
     */
    'env' => 'dev',

    // Nom affiche dans l'interface et dans le titre des pages.
    'app_name' => 'Finance',

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'finance',
        // En production, un compte dedie a cette seule base, jamais 'root' :
        // une faille applicative ne doit pas donner la main sur les autres bases.
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'finance_session',

        /*
         * true : le cookie de session n'est transmis qu'en HTTPS.
         *
         * A passer a true des que le site est servi en TLS. Laisse a false sur
         * un site accessible en clair, le cookie voyage lisible par quiconque
         * se trouve sur le reseau -- et ce cookie vaut le mot de passe.
         */
        'secure'   => false,

        // Duree d'inactivite avant deconnexion, en secondes.
        'lifetime' => 7200,
    ],
];
