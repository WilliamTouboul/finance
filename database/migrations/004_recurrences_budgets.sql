-- Operations recurrentes et budgets par pole.

-- Modele d'operation qui revient a intervalle regulier.
--
-- Il n'y a volontairement pas de colonne "jour du mois" ni "jour de la semaine" :
-- starts_on porte deja cette information. Un loyer au 5 commence le 5, une
-- echeance hebdomadaire du mardi commence un mardi. Les occurrences suivantes
-- se deduisent de la date de depart et de la frequence, ce qui evite trois
-- colonnes qui pourraient se contredire entre elles.
CREATE TABLE recurrences (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    label          VARCHAR(150) NOT NULL,
    -- Negatif = depense, positif = recette, comme pour les operations.
    amount_cents   BIGINT       NOT NULL,
    frequency      ENUM('weekly', 'monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
    -- Premiere echeance. Porte aussi le jour retenu pour toutes les suivantes.
    starts_on      DATE         NOT NULL,
    -- Fin optionnelle : un credit a 24 mensualites s'arrete tout seul.
    ends_on        DATE         NULL DEFAULT NULL,
    note           TEXT         NULL,
    primary_tag_id INT UNSIGNED NULL DEFAULT NULL,
    -- Suspendre sans supprimer : l'historique des operations deja generees
    -- doit survivre a l'arret d'un abonnement.
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recurrences_user (user_id, is_active),
    CONSTRAINT fk_recurrences_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_recurrences_primary_tag FOREIGN KEY (primary_tag_id) REFERENCES tags (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tags attaches au modele, recopies sur chaque operation generee.
CREATE TABLE recurrence_tag (
    recurrence_id INT UNSIGNED NOT NULL,
    tag_id        INT UNSIGNED NOT NULL,
    PRIMARY KEY (recurrence_id, tag_id),
    KEY idx_recurrence_tag_tag (tag_id),
    CONSTRAINT fk_recurrence_tag_recurrence FOREIGN KEY (recurrence_id) REFERENCES recurrences (id) ON DELETE CASCADE,
    CONSTRAINT fk_recurrence_tag_tag        FOREIGN KEY (tag_id)        REFERENCES tags (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance d'une operation generee depuis un modele.
--
-- Cette colonne tient lieu de memoire : les echeances restant a valider sont
-- celles qui n'ont pas encore d'operation portant ce couple (recurrence, date).
-- Un compteur "derniere echeance generee" aurait pu se desynchroniser d'une
-- suppression manuelle ; la presence de la ligne, elle, ne ment pas.
--
-- ON DELETE SET NULL : supprimer un modele ne doit pas emporter les operations
-- deja enregistrees, qui ont reellement eu lieu.
ALTER TABLE operations
    ADD COLUMN recurrence_id INT UNSIGNED NULL DEFAULT NULL AFTER primary_tag_id,
    ADD KEY idx_operations_recurrence (recurrence_id, occurred_on),
    ADD CONSTRAINT fk_operations_recurrence
        FOREIGN KEY (recurrence_id) REFERENCES recurrences (id) ON DELETE SET NULL;

-- Budget mensuel affecte a un pole.
--
-- Un seul budget par tag : raisonner "300 euros de loisirs par mois" se suffit
-- a lui-meme, et des budgets par periode compliqueraient la saisie sans rien
-- apporter tant qu'ils ne changent pas tous les mois.
CREATE TABLE budgets (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    tag_id       INT UNSIGNED NOT NULL,
    -- Toujours positif : c'est un plafond de depense, pas un montant signe.
    amount_cents BIGINT       NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_budget_user_tag (user_id, tag_id),
    CONSTRAINT fk_budgets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_tag  FOREIGN KEY (tag_id)  REFERENCES tags (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
