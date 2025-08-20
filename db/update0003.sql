CREATE TABLE `campaigns`
(
    `session`  TEXT PRIMARY KEY REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `campaign` TEXT NOT NULL,
    `source`   TEXT NULL,
    `medium`   TEXT NULL
);

CREATE INDEX `idx_campaigns_campaign` ON `campaigns` (`campaign`);
CREATE INDEX `idx_campaigns_source` ON `campaigns` (`source`);
CREATE INDEX `idx_campaigns_medium` ON `campaigns` (`medium`);
