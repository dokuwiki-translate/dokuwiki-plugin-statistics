
-- logged in users and their info and groups

CREATE TABLE `users`
(
    `user` TEXT PRIMARY KEY,
    `dt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- last seen
    `domain` TEXT DEFAULT NULL -- email domain
);

CREATE TABLE `groups`
(
    `user` TEXT NOT NULL REFERENCES `users` (`user`) ON DELETE CASCADE ON UPDATE CASCADE,
    `group` TEXT NOT NULL,
    PRIMARY KEY (`user`, `group`)
);

-- current browsing session

CREATE TABLE `sessions`
(
    `session` TEXT PRIMARY KEY,
    `dt`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uid`     TEXT    NOT NULL,
    `user`    TEXT    NOT NULL REFERENCES `users` (`user`) ON DELETE SET NULL ON UPDATE CASCADE,
    `ua`       TEXT    NOT NULL,
    `ua_info`  TEXT    NOT NULL,
    `ua_type`  TEXT    NOT NULL,
    `ua_ver`   TEXT    NOT NULL,
    `os`       TEXT    NOT NULL
);
CREATE INDEX `idx_session_dt` ON `sessions` (`dt`);
CREATE INDEX `idx_session_uid` ON `sessions` (`uid`);
CREATE INDEX `idx_session_ua_type` ON `sessions` (`ua_type`);

-- referrers

CREATE TABLE `referers`
(
    `id`      INTEGER PRIMARY KEY,
    `url`     TEXT NOT NULL,
    `dt`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type`    TEXT     NOT NULL DEFAULT 'external' -- 'external', 'search'
);
CREATE UNIQUE INDEX `idx_referers_url` ON `referers` (`url`);
CREATE INDEX `idx_referers_dt` ON `referers` (`dt`);
CREATE INDEX `idx_referers_type` ON `referers` (`type`);

-- page view logging

CREATE TABLE `pageviews`
(
    `id`       INTEGER PRIMARY KEY,
    `dt`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip`       TEXT    NOT NULL,
    `session`  TEXT    NOT NULL REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `page`     TEXT    NOT NULL,
    `ref_id`   INTEGER DEFAULT NULL REFERENCES `referers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    `screen_x` INTEGER NOT NULL,
    `screen_y` INTEGER NOT NULL,
    `view_x`   INTEGER NOT NULL,
    `view_y`   INTEGER NOT NULL
);
CREATE INDEX `idx_pageviews_page` ON `pageviews` (`page`);
CREATE INDEX `idx_pageviews_dt` ON `pageviews` (`dt`);

CREATE TABLE `iplocation`
(
    `ip`      TEXT PRIMARY KEY,
    `code`    TEXT NOT NULL,
    `country` TEXT NOT NULL,
    `city`    TEXT NOT NULL,
    `host`    TEXT NOT NULL,
    `lastupd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX `idx_iplocation_code` ON `iplocation` (`code`);

CREATE TABLE `outlinks`
(
    `id`       INTEGER PRIMARY KEY,
    `dt`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `session`  TEXT NOT NULL REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `link`     TEXT NOT NULL,
    `page`     TEXT NOT NULL DEFAULT ''
);
CREATE INDEX `idx_outlinks_link` ON `outlinks` (`link`);
CREATE INDEX `idx_outlinks_dt` ON `outlinks` (`dt`);


-- Search engine query logging for internal searches
CREATE TABLE `search`
(
    `id`    INTEGER PRIMARY KEY,
    `dt`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip`      TEXT NOT NULL,
    `session` TEXT NOT NULL REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `query`  TEXT NOT NULL
);
CREATE INDEX `idx_search_dt` ON `search` (`dt`);

CREATE TABLE `searchwords`
(
    `sid`  INTEGER NOT NULL,
    `word` TEXT    NOT NULL,
    FOREIGN KEY (`sid`) REFERENCES `search` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (`sid`, `word`)
);

-- Edit logging for content changes
CREATE TABLE `edits`
(
    `id`      INTEGER PRIMARY KEY,
    `dt`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip`      TEXT NOT NULL,
    `session` TEXT NOT NULL REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `page`    TEXT NOT NULL,
    `type`    TEXT NOT NULL
);
CREATE INDEX `idx_edits_dt` ON `edits` (`dt`);
CREATE INDEX `idx_edits_type` ON `edits` (`type`);


CREATE TABLE `logins`
(
    `id`      INTEGER PRIMARY KEY,
    `dt`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip`      TEXT NOT NULL,
    `session` TEXT NOT NULL REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `type`    TEXT NOT NULL
);
CREATE INDEX `idx_logins_dt` ON `logins` (`dt`);
CREATE INDEX `idx_logins_type` ON `logins` (`type`);



CREATE TABLE `media`
(
    `id`      INTEGER PRIMARY KEY,
    `dt`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip`      TEXT,
    `session` TEXT    NOT NULL REFERENCES `sessions` (`session`) ON DELETE CASCADE ON UPDATE CASCADE,
    `media`   TEXT    NOT NULL,
    `size`    INTEGER NOT NULL,
    `mime1`   TEXT    NOT NULL,
    `mime2`   TEXT    NOT NULL,
    `inline`  INTEGER NOT NULL
);
CREATE INDEX `idx_media_media` ON `media` (`media`);
CREATE INDEX `idx_media_dt` ON `media` (`dt`);
CREATE INDEX `idx_media_mime1` ON `media` (`mime1`);


CREATE TABLE `history`
(
    `info`  TEXT    NOT NULL,
    `dt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `value` INTEGER NOT NULL,
    PRIMARY KEY (`info`, `dt`)
);

