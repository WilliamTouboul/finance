-- Tag principal : celui qui porte le montant de l'operation dans le camembert.
--
-- Pourquoi une colonne sur operations plutot qu'un drapeau is_primary sur
-- operation_tag : une colonne unique garantit structurellement qu'il n'y a
-- qu'un seul tag principal. Un drapeau permettrait d'en cocher deux, et il
-- faudrait l'empecher a la main a chaque ecriture.
--
-- Ce que le schema ne peut pas garantir, en revanche, c'est que ce tag figure
-- bien parmi les tags de l'operation : aucune contrainte SQL simple ne
-- l'exprime. C'est assure cote applicatif, au meme endroit que le filtrage des
-- tags recus d'un formulaire.
--
-- ON DELETE SET NULL : supprimer un tag fait retomber ses operations en
-- "Non classe" plutot que de les effacer avec lui.

ALTER TABLE operations
    ADD COLUMN primary_tag_id INT UNSIGNED NULL DEFAULT NULL AFTER note,
    ADD KEY idx_operations_primary_tag (primary_tag_id),
    ADD CONSTRAINT fk_operations_primary_tag
        FOREIGN KEY (primary_tag_id) REFERENCES tags (id) ON DELETE SET NULL;

-- Reprise de l'existant : une operation ne portant qu'un seul tag n'a aucune
-- ambiguite, ce tag devient son tag principal. Aucune ressaisie n'est demandee.
UPDATE operations o
   SET o.primary_tag_id = (
       SELECT ot.tag_id
         FROM operation_tag ot
        WHERE ot.operation_id = o.id
        LIMIT 1
   )
 WHERE (
       SELECT COUNT(*)
         FROM operation_tag ot2
        WHERE ot2.operation_id = o.id
   ) = 1;
