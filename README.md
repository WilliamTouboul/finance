# Finance

Dashboard de gestion de finances personnelles. PHP 8.4, architecture MVC orientee objet,
MySQL, sans dependance externe.

## Prerequis

- PHP 8.4 avec les extensions `pdo_mysql`, `mbstring`, `openssl`
- MySQL 5.7+ ou MariaDB 10.4+

Aucune bibliotheque tierce, donc pas de `composer install` : l'autoload est assure par un
autoloader PSR-4 maison (`src/Core/Autoloader.php`).

## Installation

Si PHP n'est pas dans le PATH, sous PowerShell une commande qui commence par un
chemin entre guillemets doit etre prefixee par l'operateur d'appel `&`, sans
quoi PowerShell la prend pour une simple chaine de caracteres :

    & "C:\chemin\vers\php.exe" bin/migrate.php

Plus commode pour une session de travail, ajouter le dossier de PHP au PATH
le temps de la session :

    $env:Path = "C:\chemin\vers\le\dossier\php;$env:Path"
    php bin/migrate.php

1. Copier la configuration :

       copy config\config.example.php config\config.php

2. Renseigner les identifiants MySQL dans `config/config.php`.

3. Appliquer les migrations (la base est creee si elle n'existe pas) :

       php bin/migrate.php

4. Creer le compte utilisateur :

       php bin/create-user.php

5. Lancer le serveur de developpement :

       php -S localhost:8000 -t public

## Scripts

| Script                    | Role                                            |
|---------------------------|-------------------------------------------------|
| `bin/migrate.php`         | Applique les migrations SQL non encore jouees   |
| `bin/create-user.php`     | Cree un compte (seul moyen : pas d'inscription) |
| `bin/set-password.php`    | Definit un nouveau mot de passe sur un compte         |

## Arborescence

    public/      Seul dossier expose par le serveur web (front controller + assets)
    src/Core/    Noyau : autoload, routage, acces DB, session, CSRF, hachage
    src/         Controleurs, modeles, repositories, services
    views/       Gabarits PHP
    config/      Configuration (config.php non versionne) et table de routage
    database/    Migrations SQL
    bin/         Scripts en ligne de commande
    var/         Logs et fichiers generes

## Conventions

- Les montants sont stockes en **centimes**, dans un entier signe. Jamais de flottant :
  `0.1 + 0.2 !== 0.3` en binaire, et une erreur d'arrondi sur des comptes est inacceptable.
- Negatif = depense, positif = recette.
- `occurred_on` est la date reelle de l'operation, distincte de `created_at`.
- Une operation porte autant de tags que voulu, mais un seul **tag principal**, qui
  porte son montant dans la repartition par pole. Sans cette regle, une operation a
  deux tags compterait deux fois et le camembert depasserait les depenses reelles.
- Toute valeur affichee dans un gabarit passe par `View::e()`.
- Tout formulaire POST embarque un jeton CSRF via `Csrf::field()`.

## Securite

L'application heberge des donnees financieres personnelles. Les mesures en place :

| Mesure                            | Mise en oeuvre                                                       |
|-----------------------------------|----------------------------------------------------------------------|
| Hachage des mots de passe         | Argon2id si disponible, bcrypt cout 12 en repli, rehash automatique  |
| Injection SQL                     | Requetes preparees systematiques, emulation PDO desactivee           |
| XSS                               | Echappement a la sortie via `View::e()`                              |
| CSRF                              | Jeton par session + cookie `SameSite=Strict`                         |
| Fixation de session               | Regeneration de l'identifiant a la connexion                         |
| Vol de cookie de session          | `HttpOnly`, `Secure` (en HTTPS), empreinte du client                  |
| Force brute                       | Verrouillage 15 min au-dela de 10 echecs par IP ou 20 par compte     |
| Enumeration des comptes           | Message unique + verification factice a temps constant               |
| Exposition du code source         | Seul `public/` est expose, la configuration est hors docroot         |
| Fuite par les traces d'erreur     | Arguments retires des traces (`zend.exception_ignore_args`)          |
| Encodage des entrees              | Normalisation UTF-8 a la lecture de la requete                       |

Le mot de passe en clair n'existe que le temps de sa verification. Il n'est
jamais journalise, ni renvoye dans un formulaire, ni place dans une URL, ni
stocke en session, ni accepte en argument de ligne de commande. Le point le
moins evident est celui des traces d'erreur : par defaut PHP y joint les
arguments de chaque appel, si bien qu'une panne survenue pendant une connexion
ecrivait `Auth->attempt('vous@exemple.fr', 'VotreMotDePa...')` dans le journal.
`config/bootstrap.php` desactive ce comportement pour tous les points d'entree.

### Avant une mise en ligne

- Passer `env` a `prod` dans `config/config.php` (masque les traces d'erreur).
- Passer `session.secure` a `true` une fois le site servi en HTTPS.
- Pointer le document root de l'hebergeur sur `public/`, jamais sur la racine du projet.

## Avancement

- [x] Bloc 1 — Mise en place, base de donnees, squelette MVC
- [x] Bloc 2 — Authentification
- [x] Bloc 3 — CRUD des tags et des operations, navigation par jour / mois / annee
- [x] Bloc 4 — Camembert par pole, recherche et filtres, export CSV
- [ ] Bloc 5 — Import CSV, operations recurrentes, courbe du solde
