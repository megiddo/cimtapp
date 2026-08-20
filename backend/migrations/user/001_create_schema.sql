CREATE TABLE IF NOT EXISTS account (
    user_id TEXT PRIMARY KEY NOT NULL,
    email TEXT NOT NULL,
    password_hash TEXT,
    google_sub TEXT,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS syringe_profiles (
    id TEXT PRIMARY KEY NOT NULL,
    label TEXT NOT NULL,
    volume_ml REAL NOT NULL CHECK (volume_ml > 0),
    capacity_iu REAL NOT NULL CHECK (capacity_iu > 0),
    is_default INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS compounds (
    id TEXT PRIMARY KEY NOT NULL,
    peptide_type_id TEXT NOT NULL,
    peptide_type_slug TEXT NOT NULL,
    peptide_type_name TEXT NOT NULL,
    peptide_mg REAL NOT NULL,
    bac_water_ml REAL NOT NULL,
    compounded_at TEXT NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS uses (
    id TEXT PRIMARY KEY NOT NULL,
    compound_id TEXT NOT NULL,
    iu REAL NOT NULL,
    syringe_id TEXT,
    syringe_label TEXT,
    syringe_volume_ml REAL NOT NULL,
    syringe_capacity_iu REAL NOT NULL,
    volume_ml REAL NOT NULL,
    peptide_mg REAL NOT NULL,
    used_at TEXT NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (compound_id) REFERENCES compounds (id)
);
