-- Comptes de demonstration.
--
-- Un visiteur peut essayer l'application sans s'inscrire : un compte anonyme
-- est cree a la volee, garni d'un jeu de donnees realiste, puis detruit au
-- bout de quelques heures.
--
-- Ces comptes sont des utilisateurs comme les autres, et c'est tout l'interet :
-- la demonstration fait tourner exactement le meme code que l'application
-- reelle, sans implementation parallele qui finirait par diverger. L'isolation
-- repose sur la meme barriere que pour deux utilisateurs ordinaires -- chaque
-- requete filtre sur user_id -- deja verifiee par les tests d'acces croise.
--
-- La suppression d'un compte emporte ses tags, operations, recurrences et
-- budgets par les contraintes ON DELETE CASCADE existantes : purger revient
-- donc a supprimer les lignes expirees de cette table.

ALTER TABLE users
    ADD COLUMN is_demo    TINYINT(1) NOT NULL DEFAULT 0 AFTER display_name,
    ADD COLUMN expires_at DATETIME   NULL DEFAULT NULL AFTER is_demo,
    ADD KEY idx_users_demo (is_demo, expires_at);
