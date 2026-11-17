CREATE TABLE IF NOT EXISTS people (
    id TEXT PRIMARY KEY NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL,
    removed_at TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS people_visible_name_ci
    ON people (LOWER(name))
    WHERE removed_at IS NULL;

CREATE TABLE IF NOT EXISTS peptides (
    id TEXT PRIMARY KEY NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    removed_at TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS peptides_slug_ci ON peptides (LOWER(slug));

CREATE TABLE IF NOT EXISTS lid_colors (
    id TEXT PRIMARY KEY NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS lid_colors_name_ci ON lid_colors (LOWER(name));

CREATE TABLE IF NOT EXISTS vials (
    id TEXT PRIMARY KEY NOT NULL,
    peptide_id TEXT NOT NULL,
    peptide_name TEXT NOT NULL,
    peptide_mg REAL NOT NULL,
    bac_water_ml REAL NOT NULL,
    concentration_mg_per_ml REAL NOT NULL,
    display_name TEXT NOT NULL,
    lid_color_id TEXT,
    lid_color_name TEXT NOT NULL DEFAULT '',
    usage_offset_mg REAL NOT NULL DEFAULT 0,
    compounded_at TEXT NOT NULL,
    superseded_at TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (peptide_id) REFERENCES peptides (id),
    FOREIGN KEY (lid_color_id) REFERENCES lid_colors (id)
);

CREATE INDEX IF NOT EXISTS vials_current ON vials (superseded_at);

CREATE TABLE IF NOT EXISTS vial_people (
    vial_id TEXT NOT NULL,
    person_id TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    PRIMARY KEY (vial_id, person_id),
    FOREIGN KEY (vial_id) REFERENCES vials (id) ON DELETE CASCADE,
    FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS vial_people_person ON vial_people (person_id);

CREATE TABLE IF NOT EXISTS log_entries (
    id TEXT PRIMARY KEY NOT NULL,
    person_id TEXT,
    person_name TEXT NOT NULL,
    vial_id TEXT,
    vial_name TEXT NOT NULL DEFAULT '',
    lid_color_name TEXT NOT NULL DEFAULT '',
    peptide_name TEXT NOT NULL DEFAULT '',
    bac_water_ml REAL NOT NULL DEFAULT 0,
    peptide_mg_in_vial REAL NOT NULL DEFAULT 0,
    concentration_mg_per_ml REAL NOT NULL DEFAULT 0,
    iu REAL NOT NULL,
    dose_volume_ml REAL NOT NULL,
    dose_mg REAL NOT NULL,
    syringe_volume_ml REAL NOT NULL,
    syringe_capacity_iu REAL NOT NULL,
    logged_at TEXT NOT NULL,
    is_last_draw INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (vial_id) REFERENCES vials (id)
);

CREATE INDEX IF NOT EXISTS log_entries_person_logged
    ON log_entries (person_id, logged_at DESC, id DESC);

CREATE TABLE IF NOT EXISTS account_syringe (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    volume_ml REAL NOT NULL CHECK (volume_ml > 0),
    capacity_iu REAL NOT NULL CHECK (capacity_iu > 0)
);