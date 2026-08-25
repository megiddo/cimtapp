ALTER TABLE compounds ADD COLUMN name TEXT NOT NULL DEFAULT '';
ALTER TABLE compounds ADD COLUMN is_open INTEGER NOT NULL DEFAULT 1;

UPDATE compounds SET name = peptide_type_name WHERE name = '';
