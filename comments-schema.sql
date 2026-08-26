CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
    full_name TEXT NOT NULL,
    email TEXT NOT NULL,
    location TEXT,
    rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    body TEXT NOT NULL,
    image_filename TEXT,
    image_url TEXT,
    image_status TEXT NOT NULL DEFAULT 'none' CHECK(image_status IN ('none', 'awaiting-r2', 'ready', 'rejected')),
    consent_at TEXT NOT NULL,
    ip_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TEXT,
    reviewer_note TEXT
);

CREATE INDEX IF NOT EXISTS idx_comments_public ON comments(status, created_at DESC);
