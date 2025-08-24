-- 1. Create new table without NOT NULL on campaign
CREATE TABLE campaigns_new
(
    session  TEXT PRIMARY KEY REFERENCES sessions (session) ON DELETE CASCADE ON UPDATE CASCADE,
    campaign TEXT,
    source   TEXT,
    medium   TEXT
);

-- 2. Copy data
INSERT INTO campaigns_new (session, campaign, source, medium)
SELECT session, campaign, source, medium FROM campaigns;

-- 3. Drop old table
DROP TABLE campaigns;

-- 4. Rename new table
ALTER TABLE campaigns_new RENAME TO campaigns;

-- 5. Recreate indexes
CREATE INDEX idx_campaigns_campaign ON campaigns (campaign);
CREATE INDEX idx_campaigns_source ON campaigns (source);
CREATE INDEX idx_campaigns_medium ON campaigns (medium);
