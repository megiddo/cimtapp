ALTER TABLE syringe_profiles ADD COLUMN quantity INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS bac_bottles (
    id TEXT PRIMARY KEY NOT NULL,
    volume_ml REAL NOT NULL CHECK (volume_ml > 0),
    remaining_ml REAL NOT NULL CHECK (remaining_ml >= 0),
    opened_at TEXT NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL
);

ALTER TABLE compounds ADD COLUMN bac_bottle_id TEXT;
