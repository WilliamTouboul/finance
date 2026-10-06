<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\Frequency;
use App\Core\PasswordHasher;
use App\Model\User;
use App\Repository\BudgetRepository;
use App\Repository\OperationRepository;
use App\Repository\RecurrenceRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Comptes de demonstration.
 *
 * Un visiteur essaie l'application sans s'inscrire : on lui fabrique un compte
 * anonyme garni de donnees credibles, puis on le connecte dessus. Il dispose
 * ensuite de toutes les fonctions, sans restriction.
 *
 * Le parti pris est de ne rien simuler. La demonstration passe par le meme
 * code que l'application reelle, et son isolation repose sur la barriere qui
 * separe deja deux utilisateurs ordinaires : chaque requete filtre sur
 * user_id. Une implementation parallele en session aurait evite d'ecrire en
 * base, mais aurait double mille lignes d'acces aux donnees, avec la certitude
 * qu'elles divergeraient un jour -- et une demonstration qui ne montre plus le
 * vrai comportement ne sert a rien.
 */
final class Demo
{
    /** Duree de vie d'un compte de demonstration. */
    private const LIFETIME_HOURS = 24;

    /**
     * Nombre de comptes qu'une meme adresse IP peut ouvrir par heure.
     *
     * Sans ce plafond, une boucle automatisee remplirait la base de comptes
     * jetables. Il reste assez large pour ne jamais gener un visiteur qui
     * recommence sa visite plusieurs fois.
     */
    private const MAX_PER_IP_PER_HOUR = 10;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly TagRepository $tags = new TagRepository(),
        private readonly OperationRepository $operations = new OperationRepository(),
        private readonly RecurrenceRepository $recurrences = new RecurrenceRepository(),
        private readonly BudgetRepository $budgets = new BudgetRepository(),
    ) {
    }

    /**
     * Cree un compte de demonstration garni et le retourne.
     *
     * @throws RuntimeException si le plafond horaire est atteint pour cette IP.
     */
    public function createAccount(string $ip): User
    {
        $this->purgeExpired();

        if ($this->recentCountForIp($ip) >= self::MAX_PER_IP_PER_HOUR) {
            throw new RuntimeException(
                'Trop de démonstrations lancées depuis cette adresse. Réessayez dans une heure.'
            );
        }

        $suffix = bin2hex(random_bytes(4));

        $userId = $this->users->create(
            "demo-{$suffix}@demo.local",
            'Visiteur',
            // Un mot de passe aleatoire que personne ne connaitra jamais : on
            // n'entre dans ce compte que par le bouton de demonstration.
            PasswordHasher::hash(bin2hex(random_bytes(32))),
        );

        Database::run(
            'UPDATE users SET is_demo = 1, expires_at = (NOW() + INTERVAL ' . self::LIFETIME_HOURS . ' HOUR) WHERE id = ?',
            [$userId]
        );

        $this->seed($userId);

        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new RuntimeException('Le compte de démonstration n\'a pas pu être créé.');
        }

        return $user;
    }

    /**
     * Supprime les comptes arrives a expiration.
     *
     * Les tags, operations, recurrences et budgets partent avec, par les
     * contraintes ON DELETE CASCADE. Appele a chaque ouverture de
     * demonstration, ce qui suffit a garder la table propre sans dependre
     * d'une tache planifiee -- que tous les hebergements ne proposent pas.
     */
    public function purgeExpired(): int
    {
        $statement = Database::run(
            'DELETE FROM users WHERE is_demo = 1 AND expires_at IS NOT NULL AND expires_at < NOW()'
        );

        return $statement->rowCount();
    }

    /**
     * Garnit le compte : trois mois d'historique credible.
     *
     * Les dates sont calculees par rapport au mois courant plutot que figees :
     * une demonstration qui montrerait des operations vieilles de deux ans
     * donnerait l'impression d'une application a l'abandon.
     */
    private function seed(int $userId): void
    {
        $thisMonth = new DateTimeImmutable('first day of this month');

        $palette = [
            'logement'     => '#6366F1',
            'alimentation' => '#F59E0B',
            'transport'    => '#3B82F6',
            'loisirs'      => '#8B5CF6',
            'abonnements'  => '#EC4899',
            'salaire'      => '#10B981',
        ];

        $tagIds = [];
        foreach ($palette as $name => $color) {
            $tagIds[$name] = $this->tags->create($userId, $name, $color);
        }

        // [jour, libelle, centimes, tag] pour chacun des trois derniers mois.
        $template = [
            [1,  'Salaire',            310000, 'salaire'],
            [3,  'Loyer',              -95000, 'logement'],
            [4,  'Abonnement mobile',   -1999, 'abonnements'],
            [5,  'Courses',            -11240, 'alimentation'],
            [8,  'Essence',             -6500, 'transport'],
            [11, 'Restaurant',          -4280, 'alimentation'],
            [12, 'Abonnement vidéo',    -1399, 'abonnements'],
            [14, 'Courses',            -13670, 'alimentation'],
            [2,  'Cinéma',              -2400, 'loisirs'],
            [19, 'Électricité',         -8900, 'logement'],
            [21, 'Courses',            -10230, 'alimentation'],
            [24, 'Péage et carburant',  -5400, 'transport'],
            [6,  'Livres',              -3450, 'loisirs'],
            [26, 'Sortie',              -5600, 'loisirs'],
        ];

        // Quelques dépenses exceptionnelles, pour que la courbe ne soit pas
        // une répétition monotone et que le camembert ait du relief.
        $extras = [
            [2, 22, 'Concert',          -8900, 'loisirs'],
            [1, 15, 'Réparation vélo',  -7200, 'transport'],
            [0, 9,  'Cadeau',           -4500, 'loisirs'],
        ];

        foreach ([2, 1, 0] as $monthsAgo) {
            $month = $thisMonth->modify("-{$monthsAgo} months");

            foreach ($template as [$day, $label, $cents, $tag]) {
                $date = $this->safeDate($month, $day);

                // On ne saisit pas de dépenses à venir : le mois en cours
                // s'arrête à aujourd'hui, comme un vrai relevé.
                if ($date > new DateTimeImmutable('today')) {
                    continue;
                }

                $this->operations->create(
                    $userId,
                    $label,
                    $cents,
                    $date,
                    null,
                    [$tagIds[$tag]],
                    $tagIds[$tag],
                );
            }
        }

        foreach ($extras as [$monthsAgo, $day, $label, $cents, $tag]) {
            $date = $this->safeDate($thisMonth->modify("-{$monthsAgo} months"), $day);

            if ($date <= new DateTimeImmutable('today')) {
                $this->operations->create(
                    $userId,
                    $label,
                    $cents,
                    $date,
                    null,
                    [$tagIds[$tag]],
                    $tagIds[$tag],
                );
            }
        }

        // Deux récurrences dont les échéances du mois sont encore à valider :
        // le visiteur découvre la file d'attente sans avoir rien à préparer.
        $this->recurrences->create(
            $userId,
            'Loyer',
            -95000,
            Frequency::Monthly,
            $this->safeDate($thisMonth, 3),
            null,
            null,
            [$tagIds['logement']],
            $tagIds['logement'],
        );

        $this->recurrences->create(
            $userId,
            'Salle de sport',
            -3490,
            Frequency::Monthly,
            $this->safeDate($thisMonth->modify('-2 months'), 6),
            null,
            null,
            [$tagIds['loisirs']],
            $tagIds['loisirs'],
        );

        // Un budget confortable et un budget dépassé : les deux états se voient
        // du premier coup d'oeil.
        $this->budgets->save($userId, $tagIds['alimentation'], 40000);
        $this->budgets->save($userId, $tagIds['loisirs'], 10000);
    }

    /**
     * Date du mois donne, ramenee au dernier jour si le mois est trop court.
     */
    private function safeDate(DateTimeImmutable $month, int $day): DateTimeImmutable
    {
        $first = $month->modify('first day of this month')->setTime(0, 0);

        return $first->setDate(
            (int) $first->format('Y'),
            (int) $first->format('n'),
            min($day, (int) $first->format('t'))
        );
    }

    /**
     * Comptes de demonstration ouverts par cette IP dans la derniere heure.
     *
     * La table login_attempts sert de journal : chaque ouverture y est
     * enregistree comme une connexion reussie sous un email reserve, ce qui
     * evite une table supplementaire pour un compteur.
     */
    private function recentCountForIp(string $ip): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
              WHERE email = ?
                AND ip_address = ?
                AND attempted_at > (NOW() - INTERVAL 1 HOUR)',
            ['[demo]', self::packIp($ip)]
        );
    }

    /**
     * Inscrit l'ouverture au journal, pour le plafond par adresse.
     */
    public function recordOpening(string $ip): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip_address, email, succeeded) VALUES (?, ?, 1)',
            [self::packIp($ip), '[demo]']
        );
    }

    private static function packIp(string $ip): string
    {
        $packed = @inet_pton($ip);

        return $packed === false ? (string) inet_pton('0.0.0.0') : $packed;
    }
}
