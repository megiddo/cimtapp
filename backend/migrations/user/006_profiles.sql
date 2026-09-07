CREATE TABLE IF NOT EXISTS profiles (
    id TEXT PRIMARY KEY NOT NULL,
    name TEXT NOT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS compound_profiles (
    compound_id TEXT NOT NULL,
    profile_id TEXT NOT NULL,
    PRIMARY KEY (compound_id, profile_id),
    FOREIGN KEY (compound_id) REFERENCES compounds (id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS compound_profiles_profile_id
    ON compound_profiles (profile_id);

ALTER TABLE uses ADD COLUMN profile_id TEXT REFERENCES profiles (id);
