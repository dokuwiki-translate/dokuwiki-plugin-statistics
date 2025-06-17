CREATE TABLE `stats_access` (
  `id`       INTEGER PRIMARY KEY AUTOINCREMENT,
  `dt`       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `page`     TEXT NOT NULL,
  `ip`       TEXT NOT NULL,
  `ua`       TEXT NOT NULL,
  `ua_info`  TEXT NOT NULL,
  `ua_type`  TEXT NOT NULL,
  `ua_ver`   TEXT NOT NULL,
  `os`       TEXT NOT NULL,
  `ref_md5`  TEXT NOT NULL,
  `ref_type` TEXT NOT NULL,
  `ref`      TEXT NOT NULL,
  `screen_x` INTEGER NOT NULL,
  `screen_y` INTEGER NOT NULL,
  `view_x`   INTEGER NOT NULL,
  `view_y`   INTEGER NOT NULL,
  `user`     TEXT NOT NULL,
  `session`  TEXT NOT NULL
);

CREATE INDEX `idx_stats_access_ref_type` ON `stats_access` (`ref_type`);
CREATE INDEX `idx_stats_access_page` ON `stats_access` (`page`);
CREATE INDEX `idx_stats_access_ref_md5` ON `stats_access` (`ref_md5`);
CREATE INDEX `idx_stats_access_dt` ON `stats_access` (`dt`);

CREATE TABLE `stats_iplocation` (
  `ip`      TEXT PRIMARY KEY,
  `code`    TEXT NOT NULL,
  `country` TEXT NOT NULL,
  `city`    TEXT NOT NULL,
  `host`    TEXT NOT NULL,
  `lastupd` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX `idx_stats_iplocation_code` ON `stats_iplocation` (`code`);

-- UPGRADE added 2007-01-28
-- SQLite doesn't support CHANGE COLUMN, dt is already TEXT which works for datetime
ALTER TABLE `stats_access` ADD COLUMN `js` INTEGER NOT NULL DEFAULT 1;

-- UPGRADE added 2007-01-31
ALTER TABLE `stats_access` ADD COLUMN `uid` TEXT NOT NULL DEFAULT '';

CREATE TABLE `stats_outlinks` (
  `id`       INTEGER PRIMARY KEY AUTOINCREMENT,
  `dt`       TEXT NOT NULL,
  `session`  TEXT NOT NULL,
  `link_md5` TEXT NOT NULL,
  `link`     TEXT NOT NULL
);

CREATE INDEX `idx_stats_outlinks_link_md5` ON `stats_outlinks` (`link_md5`);

-- UPGRADE added 2007-02-04
ALTER TABLE `stats_outlinks` ADD COLUMN `page` TEXT NOT NULL DEFAULT '';

CREATE TABLE `stats_search` (
  `id`     INTEGER PRIMARY KEY AUTOINCREMENT,
  `dt`     TEXT NOT NULL,
  `page`   TEXT NOT NULL,
  `query`  TEXT NOT NULL,
  `engine` TEXT NOT NULL
);

CREATE TABLE `stats_searchwords` (
  `sid`  INTEGER NOT NULL,
  `word` TEXT NOT NULL,
  PRIMARY KEY (`sid`, `word`)
);

-- statistic fixes
UPDATE stats_access
SET ref_type='external'
WHERE ref LIKE 'http://digg.com/%';
UPDATE stats_access
SET ref_type='external'
WHERE ref LIKE 'http://del.icio.us/%';
UPDATE stats_access
SET ref_type='external'
WHERE ref LIKE 'http://www.stumbleupon.com/%';
UPDATE stats_access
SET ref_type='external'
WHERE ref LIKE 'http://swik.net/%';
UPDATE stats_access
SET ref_type='external'
WHERE ref LIKE 'http://segnalo.alice.it/%';

-- UPGRADE added 2008-06-15
CREATE TABLE `stats_refseen` (
  `ref_md5` TEXT PRIMARY KEY,
  `dt`      TEXT NOT NULL
);

CREATE INDEX `idx_stats_refseen_dt` ON `stats_refseen` (`dt`);

-- This will take some time...
INSERT INTO stats_refseen (`ref_md5`, `dt`) SELECT
                                          `ref_md5`,
                                          MIN(`dt`)
                                        FROM stats_access
                                        GROUP BY `ref_md5`;

-- UPGRADE added 2012-02-08
CREATE TABLE `stats_edits` (
  `id`      INTEGER PRIMARY KEY AUTOINCREMENT,
  `dt`      TEXT NOT NULL,
  `ip`      TEXT NOT NULL,
  `user`    TEXT NOT NULL,
  `session` TEXT NOT NULL,
  `uid`     TEXT NOT NULL,
  `page`    TEXT NOT NULL,
  `type`    TEXT NOT NULL
);

-- SQLite doesn't support CHANGE COLUMN, ip column already exists as TEXT

CREATE INDEX `idx_stats_search_engine` ON `stats_search` (`engine`);

CREATE TABLE `stats_session` (
  `session` TEXT PRIMARY KEY,
  `dt`      TEXT NOT NULL,
  `end`     TEXT NOT NULL,
  `views`   INTEGER NOT NULL,
  `uid`     TEXT NOT NULL
);

CREATE TABLE `stats_logins` (
  `id`      INTEGER PRIMARY KEY AUTOINCREMENT,
  `dt`      TEXT NOT NULL,
  `ip`      TEXT NOT NULL,
  `user`    TEXT NOT NULL,
  `session` TEXT NOT NULL,
  `uid`     TEXT NOT NULL,
  `type`    TEXT NOT NULL
);

CREATE INDEX `idx_stats_edits_dt` ON `stats_edits` (`dt`);
CREATE INDEX `idx_stats_edits_type` ON `stats_edits` (`type`);
CREATE INDEX `idx_stats_logins_dt` ON `stats_logins` (`dt`);
CREATE INDEX `idx_stats_logins_type` ON `stats_logins` (`type`);
CREATE INDEX `idx_stats_outlinks_dt` ON `stats_outlinks` (`dt`);
CREATE INDEX `idx_stats_search_dt` ON `stats_search` (`dt`);
CREATE INDEX `idx_stats_session_dt` ON `stats_session` (`dt`);
CREATE INDEX `idx_stats_session_views` ON `stats_session` (`views`);
CREATE INDEX `idx_stats_session_uid` ON `stats_session` (`uid`);
CREATE INDEX `idx_stats_access_ua_type` ON `stats_access` (`ua_type`);

-- UPGRADE added 2014-06-18
CREATE TABLE `stats_lastseen` (
  `user` TEXT PRIMARY KEY,
  `dt`   TEXT NOT NULL
);

CREATE TABLE `stats_media` (
  `id`      INTEGER PRIMARY KEY AUTOINCREMENT,
  `dt`      TEXT NOT NULL,
  `media`   TEXT NOT NULL,
  `ip`      TEXT,
  `ua`      TEXT NOT NULL,
  `ua_info` TEXT NOT NULL,
  `ua_type` TEXT NOT NULL,
  `ua_ver`  TEXT NOT NULL,
  `os`      TEXT NOT NULL,
  `user`    TEXT NOT NULL,
  `session` TEXT NOT NULL,
  `uid`     TEXT NOT NULL,
  `size`    INTEGER NOT NULL,
  `mime1`   TEXT NOT NULL,
  `mime2`   TEXT NOT NULL,
  `inline`  INTEGER NOT NULL
);

CREATE INDEX `idx_stats_media_media` ON `stats_media` (`media`);
CREATE INDEX `idx_stats_media_dt` ON `stats_media` (`dt`);
CREATE INDEX `idx_stats_media_ua_type` ON `stats_media` (`ua_type`);

CREATE INDEX `idx_stats_media_mime1` ON `stats_media` (`mime1`);

CREATE TABLE `stats_history` (
  `info`    TEXT NOT NULL,
  `dt`      TEXT NOT NULL,
  `value`   INTEGER NOT NULL,
  PRIMARY KEY (`info`, `dt`)
);

CREATE TABLE `stats_groups` (
  `id`      INTEGER PRIMARY KEY AUTOINCREMENT,
  `dt`      TEXT NOT NULL,
  `group`   TEXT NOT NULL,
  `type`    TEXT NOT NULL
);

CREATE INDEX `idx_stats_groups_dt` ON `stats_groups` (`dt`);
CREATE INDEX `idx_stats_groups_type` ON `stats_groups` (`type`);

-- UPGRADE added 2019-04-10
-- SQLite INTEGER can handle large values, no modification needed

-- UPGRADE added 2023-12-08
-- SQLite TEXT columns already handle variable length strings, no modification needed
