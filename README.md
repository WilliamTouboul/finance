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
| `bin/test.php`            | Lance les suites de tests (aucune ne touche a la base) |
| `bin/check-prod.php`      | Verifie une installation avant ouverture au public    |

## Arborescence

    public/      Seul dossier expose par le serveur web (front controller + assets)
    src/Core/    Noyau : autoload, routage, acces DB, session, CSRF, hachage
    src/         Controleurs, modeles, repositories, services
    views/       Gabarits PHP
    config/      Configuration (config.php non versionne) et table de routage
    database/    Migrations SQL
    bin/         Scripts en ligne de commande
    tests/       Suites de tests, lancees par bin/test.php
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

## Demonstration

Un visiteur peut essayer l'application sans compte depuis `/demo`, ou depuis le
bouton place sous le formulaire de connexion. Un compte anonyme est cree a la
volee, garni de trois mois d'operations, de tags, de recurrences et de budgets,
puis detruit au bout de 24 heures.

Il ne s'agit pas d'une simulation : la demonstration fait tourner exactement le
meme code que l'application reelle. Une implementation parallele aurait double
un millier de lignes d'acces aux donnees, avec la certitude qu'elles finiraient
par diverger -- et une demonstration qui ne montre plus le vrai comportement ne
sert a rien. L'isolation repose sur la barriere qui separe deja deux
utilisateurs ordinaires : chaque requete filtre sur `user_id`.

L'ouverture passe par POST et non par GET, car creer un compte est une ecriture :
un lien serait declenche par les prechargements de navigateur et les apercus de
messagerie. La purge des comptes expires est opportuniste, declenchee a chaque
ouverture, ce qui evite de dependre d'une tache planifiee que tous les
hebergements ne proposent pas.

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

## Deploiement

L'application tourne sur n'importe quel hebergement mutualise offrant PHP 8.2+
et MySQL ou MariaDB. La procedure ci-dessous est ecrite pour alwaysdata, dont
l'offre gratuite suffit largement : 1 Go de disque, un sous-domaine en
`.alwaysdata.net` et un certificat Let's Encrypt automatique.

### 1. Compte et site

1. Creer un compte sur alwaysdata, offre Free.
2. Dans **Web > Sites**, ajouter un site.
3. **Adresse** : `votrenom.alwaysdata.net`.
4. **Type** : PHP, version 8.2 ou superieure.
5. **Racine** : `/www/finance/public` -- et surtout pas `/www/finance`.
   C'est le reglage le plus important de toute la procedure : pointer sur la
   racine du projet rendrait `config/config.php` et ses identifiants
   telechargeables par n'importe qui.

### 2. Base de donnees

1. Dans **Bases de donnees > MySQL**, creer une base `finance`.
2. Creer un **utilisateur dedie** a cette base, avec un mot de passe long.
   Ne pas reutiliser le compte d'administration : une faille applicative ne
   doit pas donner la main sur les autres bases du compte.
3. Noter l'hote indique par alwaysdata, qui n'est pas `127.0.0.1`.

### 3. Envoi des fichiers

En SSH, ce qui rend les mises a jour suivantes triviales :

    ssh votrecompte@ssh-votrecompte.alwaysdata.net
    cd www
    git clone https://github.com/VOTRE-COMPTE/finance.git
    cd finance

A defaut, un envoi par SFTP du dossier complet fonctionne tout aussi bien.

### 4. Configuration

    cp config/config.example.php config/config.php

Puis editer `config/config.php` :

- `env` a `'prod'`
- `session.secure` a `true` (alwaysdata fournit HTTPS)
- `db.host`, `db.name`, `db.user`, `db.password` avec les valeurs de l'etape 2

### 5. Base et compte

    php bin/migrate.php
    php bin/create-user.php

La premiere commande cree les tables, la seconde votre compte. Il n'existe
aucune page d'inscription : c'est le seul moyen d'ouvrir un acces.

### 6. Verification

    php bin/check-prod.php

Ce script passe en revue la configuration, l'environnement, la base et
l'exposition des fichiers. Il refuse de valider tant qu'un point bloquant
subsiste, et renvoie un code de sortie exploitable. Ne pas ouvrir l'acces
avant qu'il soit vert.

### Mises a jour suivantes

    cd ~/www/finance
    git pull
    php bin/migrate.php
    php bin/check-prod.php

`config/config.php` n'etant pas versionne, un `git pull` ne l'ecrase jamais.

### Sauvegardes

L'offre gratuite conserve trois jours d'historique, ce qui est court pour des
donnees saisies a la main pendant des annees. L'export CSV de la page
Operations permet de garder une copie chez soi : le faire de temps en temps.

## Avancement

- [x] Bloc 1 — Mise en place, base de donnees, squelette MVC
- [x] Bloc 2 — Authentification
- [x] Bloc 3 — CRUD des tags et des operations, navigation par jour / mois / annee
- [x] Bloc 4 — Camembert par pole, recherche et filtres, export CSV
- [x] Bloc 5 — Operations recurrentes, courbe du solde, budgets par pole
