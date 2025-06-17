CREATE TABLE `access`
(
    `id`       INTEGER PRIMARY KEY,
    `dt`       TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `page`     TEXT    NOT NULL,
    `ip`       TEXT    NOT NULL,
    `ua`       TEXT    NOT NULL,
    `ua_info`  TEXT    NOT NULL,
    `ua_type`  TEXT    NOT NULL,
    `ua_ver`   TEXT    NOT NULL,
    `os`       TEXT    NOT NULL,
    `ref_md5`  TEXT    NOT NULL,
    `ref_type` TEXT    NOT NULL,
    `ref`      TEXT    NOT NULL,
    `screen_x` INTEGER NOT NULL,
    `screen_y` INTEGER NOT NULL,
    `view_x`   INTEGER NOT NULL,
    `view_y`   INTEGER NOT NULL,
    `user`     TEXT    NOT NULL,
    `session`  TEXT    NOT NULL,
    `js`       INTEGER NOT NULL DEFAULT 1,
    `uid`      TEXT    NOT NULL DEFAULT ''
);
CREATE INDEX `idx_access_ref_type` ON `access` (`ref_type`);
CREATE INDEX `idx_access_page` ON `access` (`page`);
CREATE INDEX `idx_access_ref_md5` ON `access` (`ref_md5`);
CREATE INDEX `idx_access_dt` ON `access` (`dt`);
CREATE INDEX `idx_access_ua_type` ON `access` (`ua_type`);


CREATE TABLE `iplocation`
(
    `ip`      TEXT PRIMARY KEY,
    `code`    TEXT NOT NULL,
    `country` TEXT NOT NULL,
    `city`    TEXT NOT NULL,
    `host`    TEXT NOT NULL,
    `lastupd` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX `idx_iplocation_code` ON `iplocation` (`code`);

CREATE TABLE `outlinks`
(
    `id`       INTEGER PRIMARY KEY,
    `dt`       TEXT NOT NULL,
    `session`  TEXT NOT NULL,
    `link_md5` TEXT NOT NULL,
    `link`     TEXT NOT NULL,
    `page`     TEXT NOT NULL DEFAULT ''
);
CREATE INDEX `idx_outlinks_link_md5` ON `outlinks` (`link_md5`);
CREATE INDEX `idx_outlinks_dt` ON `outlinks` (`dt`);


CREATE TABLE `search`
(
    `id`     INTEGER PRIMARY KEY,
    `dt`     TEXT NOT NULL,
    `page`   TEXT NOT NULL,
    `query`  TEXT NOT NULL,
    `engine` TEXT NOT NULL
);
CREATE INDEX `idx_search_engine` ON `search` (`engine`);
CREATE INDEX `idx_search_dt` ON `search` (`dt`);

CREATE TABLE `searchwords`
(
    `sid`  INTEGER NOT NULL,
    `word` TEXT    NOT NULL,
    PRIMARY KEY (`sid`, `word`)
);

CREATE TABLE `refseen`
(
    `ref_md5` TEXT PRIMARY KEY,
    `dt`      TEXT NOT NULL
);
CREATE INDEX `idx_refseen_dt` ON `refseen` (`dt`);

CREATE TABLE `edits`
(
    `id`      INTEGER PRIMARY KEY,
    `dt`      TEXT NOT NULL,
    `ip`      TEXT NOT NULL,
    `user`    TEXT NOT NULL,
    `session` TEXT NOT NULL,
    `uid`     TEXT NOT NULL,
    `page`    TEXT NOT NULL,
    `type`    TEXT NOT NULL
);
CREATE INDEX `idx_edits_dt` ON `edits` (`dt`);
CREATE INDEX `idx_edits_type` ON `edits` (`type`);


CREATE TABLE `session`
(
    `session` TEXT PRIMARY KEY,
    `dt`      TEXT    NOT NULL,
    `end`     TEXT    NOT NULL,
    `views`   INTEGER NOT NULL,
    `uid`     TEXT    NOT NULL
);
CREATE INDEX `idx_session_dt` ON `session` (`dt`);
CREATE INDEX `idx_session_views` ON `session` (`views`);
CREATE INDEX `idx_session_uid` ON `session` (`uid`);


CREATE TABLE `logins`
(
    `id`      INTEGER PRIMARY KEY,
    `dt`      TEXT NOT NULL,
    `ip`      TEXT NOT NULL,
    `user`    TEXT NOT NULL,
    `session` TEXT NOT NULL,
    `uid`     TEXT NOT NULL,
    `type`    TEXT NOT NULL
);
CREATE INDEX `idx_logins_dt` ON `logins` (`dt`);
CREATE INDEX `idx_logins_type` ON `logins` (`type`);


CREATE TABLE `lastseen`
(
    `user` TEXT PRIMARY KEY,
    `dt`   TEXT NOT NULL
);

CREATE TABLE `media`
(
    `id`      INTEGER PRIMARY KEY,
    `dt`      TEXT    NOT NULL,
    `media`   TEXT    NOT NULL,
    `ip`      TEXT,
    `ua`      TEXT    NOT NULL,
    `ua_info` TEXT    NOT NULL,
    `ua_type` TEXT    NOT NULL,
    `ua_ver`  TEXT    NOT NULL,
    `os`      TEXT    NOT NULL,
    `user`    TEXT    NOT NULL,
    `session` TEXT    NOT NULL,
    `uid`     TEXT    NOT NULL,
    `size`    INTEGER NOT NULL,
    `mime1`   TEXT    NOT NULL,
    `mime2`   TEXT    NOT NULL,
    `inline`  INTEGER NOT NULL
);
CREATE INDEX `idx_media_media` ON `media` (`media`);
CREATE INDEX `idx_media_dt` ON `media` (`dt`);
CREATE INDEX `idx_media_ua_type` ON `media` (`ua_type`);
CREATE INDEX `idx_media_mime1` ON `media` (`mime1`);


CREATE TABLE `history`
(
    `info`  TEXT    NOT NULL,
    `dt`    TEXT    NOT NULL,
    `value` INTEGER NOT NULL,
    PRIMARY KEY (`info`, `dt`)
);

CREATE TABLE `groups`
(
    `id`    INTEGER PRIMARY KEY,
    `dt`    TEXT NOT NULL,
    `group` TEXT NOT NULL,
    `type`  TEXT NOT NULL
);
CREATE INDEX `idx_groups_dt` ON `groups` (`dt`);
CREATE INDEX `idx_groups_type` ON `groups` (`type`);









