<?php

declare(strict_types=1);

use App\Core\Autoloader;

/**
 * Amorcage commun a tous les points d'entree, web comme ligne de commande.
 *
 * Centralise ici plutot que recopie dans chaque script : une protection que
 * l'on doit penser a repeter finit par etre oubliee quelque part, et c'est
 * precisement le point d'entree oublie qui fuite.
 *
 * @return string la racine du projet.
 */

$root = dirname(__DIR__);

require $root . '/src/Core/Autoloader.php';
Autoloader::register($root . '/src');

/*
 * Retire les arguments des traces d'exception.
 *
 * Par defaut, PHP joint a chaque ligne de trace les arguments recus par la
 * fonction. Une panne de base survenue pendant une connexion produirait donc
 * une trace du type :
 *
 *     #3 Auth->attempt('vous@exemple.fr', 'VotreMotDePa...')
 *
 * soit le mot de passe en clair dans var/log/php-error.log, et affiche a
 * l'ecran tant que l'application tourne en mode developpement. Le mot de passe
 * ne doit exister en clair que le temps de sa verification, jamais sur disque.
 *
 * La seconde directive ramene a zero la longueur des chaines conservees, au
 * cas ou la premiere serait ignoree par une version de PHP plus ancienne.
 */
ini_set('zend.exception_ignore_args', '1');
ini_set('zend.exception_string_param_max_len', '0');

ini_set('log_errors', '1');
ini_set('error_log', $root . '/var/log/php-error.log');
error_reporting(E_ALL);

date_default_timezone_set('Europe/Paris');

return $root;
