-- Schema initial : utilisateurs, operations, tags.
-- Les montants sont stockes en centimes dans un entier signe (jamais de flottant).

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(100) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(50)  NOT NULL,
    color      CHAR(7)      NOT NULL DEFAULT '#64748B',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Un meme libelle ne peut pas exister deux fois pour un meme utilisateur.
    UNIQUE KEY uniq_tags_user_name (user_id, name),
    CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operations (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    label        VARCHAR(150) NOT NULL,
    -- Negatif = depense, positif = recette. En centimes.
    amount_cents BIGINT       NOT NULL,
    -- Date reelle de l'operation, distincte de la date de saisie.
    occurred_on  DATE         NOT NULL,
    note         TEXT         NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Index principal : le dashboard liste les operations d'un utilisateur par date decroissante.
    KEY idx_operations_user_date (user_id, occurred_on, id),
    CONSTRAINT fk_operations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operation_tag (
    operation_id INT UNSIGNED NOT NULL,
    tag_id       INT UNSIGNED NOT NULL,
    PRIMARY KEY (operation_id, tag_id),
    KEY idx_operation_tag_tag (tag_id),
    CONSTRAINT fk_operation_tag_operation FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE,
    CONSTRAINT fk_operation_tag_tag       FOREIGN KEY (tag_id)       REFERENCES tags (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
