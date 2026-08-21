CREATE TABLE IF NOT EXISTS rate_limit_hits (
    bucket TEXT NOT NULL,
    hit_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS rate_limit_hits_bucket_at ON rate_limit_hits (bucket, hit_at);
