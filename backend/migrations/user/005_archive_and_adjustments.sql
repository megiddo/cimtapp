ALTER TABLE compounds ADD COLUMN archived_at TEXT;
ALTER TABLE bac_bottles ADD COLUMN archived_at TEXT;

CREATE TABLE IF NOT EXISTS compound_adjustments (
    id TEXT PRIMARY KEY NOT NULL,
    compound_id TEXT NOT NULL,
    delta_mg REAL NOT NULL,
    remaining_ml REAL NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (compound_id) REFERENCES compounds (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS compound_adjustments_compound_id
    ON compound_adjustments (compound_id);
