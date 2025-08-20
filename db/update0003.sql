CREATE TABLE `campaigns`
(
    `session`  TEXT PRIMARY KEY REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `campaign` TEXT NOT NULL,
    `source`   TEXT NULL,
    `medium`   TEXT NULL
);
