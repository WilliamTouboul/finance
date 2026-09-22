-- Historique des tentatives de connexion, utilise pour le verrouillage
-- progressif apres plusieurs echecs (protection contre la force brute).
--
-- On enregistre l'adresse IP ET l'email saisi : bloquer uniquement sur l'IP
-- laisse passer une attaque distribuee, bloquer uniquement sur l'email permet
-- a un tiers de verrouiller volontairement un compte. Les deux compteurs sont
-- evalues separement.

CREATE TABLE login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARBINARY(16) NOT NULL,
    email        VARCHAR(190)  NOT NULL,
    succeeded    TINYINT(1)    NOT NULL DEFAULT 0,
    attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_ip   (ip_address, attempted_at),
    KEY idx_attempts_mail (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
