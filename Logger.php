<?php

namespace dokuwiki\plugin\statistics;

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use DeviceDetector\Parser\OperatingSystem;
use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\Utf8\Clean;
use helper_plugin_popularity;
use helper_plugin_statistics;


class Logger
{
    /** @var helper_plugin_statistics The statistics helper plugin instance */
    protected helper_plugin_statistics $hlp;

    /** @var SQLiteDB The SQLite database instance */
    protected SQLiteDB $db;

    /** @var string The full user agent string */
    protected string $uaAgent;

    /** @var string The type of user agent (browser, robot, feedreader) */
    protected string $uaType = 'browser';

    /** @var string The browser/client name */
    protected string $uaName;

    /** @var string The browser/client version */
    protected string $uaVersion;

    /** @var string The operating system/platform */
    protected string $uaPlatform;

    /** @var string The unique user identifier */
    protected string $uid;

    /** @var DokuHTTPClient|null The HTTP client instance for testing */
    protected ?DokuHTTPClient $httpClient = null;


    /**
     * Constructor
     *
     * Parses browser info and set internal vars
     */
    public function __construct(helper_plugin_statistics $hlp, ?DokuHTTPClient $httpClient = null)
    {
        global $INPUT;

        $this->hlp = $hlp;
        $this->db = $this->hlp->getDB();
        $this->httpClient = $httpClient;

        $ua = trim($INPUT->server->str('HTTP_USER_AGENT'));

        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_MAJOR);
        $dd = new DeviceDetector($ua); // FIXME we could use client hints, but need to add headers
        $dd->discardBotInformation();
        $dd->parse();

        if ($dd->isFeedReader()) {
            $this->uaType = 'feedreader';
        } else if ($dd->isBot()) {
            $this->uaType = 'robot';

            // for now ignore bots
            throw new \RuntimeException('Bot detected, not logging');
        }

        $this->uaAgent = $ua;
        $this->uaName = Browser::getBrowserFamily($dd->getClient('name')) ?: 'Unknown';
        $this->uaVersion = $dd->getClient('version') ?: '0';
        $this->uaPlatform = OperatingSystem::getOsFamily($dd->getOs('name')) ?: 'Unknown';
        $this->uid = $this->getUID();


        $this->logLastseen();
    }

    /**
     * Should be called before logging
     *
     * This starts a transaction, so all logging is done in one go
     */
    public function begin(): void
    {
        $this->hlp->getDB()->getPdo()->beginTransaction();
    }

    /**
     * Should be called after logging
     *
     * This commits the transaction started in begin()
     */
    public function end(): void
    {
        $this->hlp->getDB()->getPdo()->commit();
    }

    /**
     * Get the unique user ID
     *
     * @return string The unique user identifier
     */
    protected function getUID(): string
    {
        global $INPUT;

        $uid = $INPUT->str('uid');
        if (!$uid) $uid = get_doku_pref('plgstats', false);
        if (!$uid) $uid = session_id();
        set_doku_pref('plgstats', $uid);
        return $uid;
    }

    /**
     * Return the user's session ID
     *
     * This is usually our own managed session, not a PHP session (only in fallback)
     *
     * @return string The session identifier
     */
    protected function getSession(): string
    {
        global $INPUT;

        $ses = $INPUT->str('ses');
        if (!$ses) $ses = get_doku_pref('plgstatsses', false);
        if (!$ses) $ses = session_id();
        set_doku_pref('plgstatsses', $ses);
        return $ses;
    }

    /**
     * Log that we've seen the user (authenticated only)
     */
    public function logLastseen(): void
    {
        global $INPUT;

        if (empty($INPUT->server->str('REMOTE_USER'))) return;

        $this->db->exec(
            'REPLACE INTO lastseen (user, dt) VALUES (?, CURRENT_TIMESTAMP)',
            $INPUT->server->str('REMOTE_USER'),
        );
    }

    /**
     * Log actions by groups
     *
     * @param string $type The type of access to log ('view','edit')
     * @param array $groups The groups to log
     */
    public function logGroups(string $type, array $groups): void
    {
        if (!$groups) return;

        $toLog = (array)$this->hlp->getConf('loggroups');
        $groups = array_intersect($groups, $toLog);
        if (!$groups) return;

        $placeholders = join(',', array_fill(0, count($groups), '(?, ?)'));
        $params = [];
        $sql = "INSERT INTO groups (`type`, `group`) VALUES $placeholders";
        foreach ($groups as $group) {
            $params[] = $type;
            $params[] = $group;
        }
        $sql = rtrim($sql, ',');
        $this->db->exec($sql, $params);
    }

    /**
     * Log external search queries
     *
     * Will not write anything if the referer isn't a search engine
     *
     * @param string $referer The HTTP referer URL
     * @param string $type Reference to the type variable that will be modified
     */
    public function logExternalSearch(string $referer, string &$type): void
    {
        global $INPUT;

        $searchEngine = new SearchEngines($referer);

        if (!$searchEngine->isSearchEngine()) {
            return; // not a search engine
        }

        $type = 'search';
        $query = $searchEngine->getQuery();

        // log it!
        $words = explode(' ', Clean::stripspecials($query, ' ', '\._\-:\*'));
        $this->logSearch($INPUT->str('p'), $query, $words, $searchEngine->getEngine());
    }

    /**
     * Log search data to the search related tables
     *
     * @param string $page The page being searched from
     * @param string $query The search query
     * @param array $words Array of search words
     * @param string $engine The search engine name
     */
    public function logSearch(string $page, string $query, array $words, string $engine): void
    {
        $sid = $this->db->exec(
            'INSERT INTO search (dt, page, query, engine) VALUES (CURRENT_TIMESTAMP, ?, ?, ?)',
            $page, $query, $engine
        );
        if (!$sid) return;

        foreach ($words as $word) {
            if (!$word) continue;
            $this->db->exec(
                'INSERT INTO searchwords (sid, word) VALUES (?, ?)',
                $sid, $word
            );
        }
    }

    /**
     * Log that the session was seen
     *
     * This is used to calculate the time people spend on the whole site
     * during their session
     *
     * Viewcounts are used for bounce calculation
     *
     * @param int $addview set to 1 to count a view
     */
    public function logSession(int $addview = 0): void
    {
        // only log browser sessions
        if ($this->uaType != 'browser') return;

        $session = $this->getSession();
        $this->db->exec(
            'INSERT OR REPLACE INTO session (
                session, dt, end, views, uid
             ) VALUES (
                ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP,
                COALESCE((SELECT views FROM session WHERE session = ?) + ?, ?),
                ?
             )',
            $session, $session, $addview, $addview, $this->uid
        );
    }

    /**
     * Resolve IP to country/city and store in database
     *
     * @param string $ip The IP address to resolve
     */
    public function logIp(string $ip): void
    {
        // check if IP already known and up-to-date
        $result = $this->db->queryValue(
            "SELECT ip
             FROM   iplocation
             WHERE  ip = ?
               AND  lastupd > date('now', '-30 days')",
            $ip
        );
        if ($result) return;

        $http = $this->httpClient ?: new DokuHTTPClient();
        $http->timeout = 10;
        $json = $http->get('http://ip-api.com/json/' . $ip); // yes, it's HTTP only

        if (!$json) return; // FIXME log error
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return; // FIXME log error
        }
        if (!isset($data['status']) || $data['status'] !== 'success') {
            return; // FIXME log error
        }

        $host = gethostbyaddr($ip);
        $this->db->exec(
            'INSERT OR REPLACE INTO iplocation (
                    ip, country, code, city, host, lastupd
                 ) VALUES (
                    ?, ?, ?, ?, ?, CURRENT_TIMESTAMP
                 )',
            $ip, $data['country'], $data['countryCode'], $data['city'], $host
        );
    }

    /**
     * Log a click on an external link
     *
     * Called from log.php
     */
    public function logOutgoing(): void
    {
        global $INPUT;

        if (!$INPUT->str('ol')) return;

        $link = $INPUT->str('ol');
        $link_md5 = md5($link);
        $session = $this->getSession();
        $page = $INPUT->str('p');

        $this->db->exec(
            'INSERT INTO outlinks (
                dt, session, page, link_md5, link
             ) VALUES (
                CURRENT_TIMESTAMP, ?, ?, ?, ?
             )',
            $session, $page, $link_md5, $link
        );
    }

    /**
     * Log a page access
     *
     * Called from log.php
     */
    public function logAccess(): void
    {
        global $INPUT, $USERINFO;

        if (!$INPUT->str('p')) return;

        # FIXME check referer against blacklist and drop logging for bad boys

        // handle referer
        $referer = trim($INPUT->str('r'));
        if ($referer) {
            $ref = $referer;
            $ref_md5 = md5($referer);
            if (str_starts_with($referer, DOKU_URL)) {
                $ref_type = 'internal';
            } else {
                $ref_type = 'external';
                $this->logExternalSearch($referer, $ref_type);
            }
        } else {
            $ref = '';
            $ref_md5 = '';
            $ref_type = '';
        }

        $page = $INPUT->str('p');
        $ip = clientIP(true);
        $sx = $INPUT->int('sx');
        $sy = $INPUT->int('sy');
        $vx = $INPUT->int('vx');
        $vy = $INPUT->int('vy');
        $js = $INPUT->int('js');
        $user = $INPUT->server->str('REMOTE_USER');
        $session = $this->getSession();

        $this->db->exec(
            'INSERT INTO access (
                dt, page, ip, ua, ua_info, ua_type, ua_ver, os, ref, ref_md5, ref_type,
                screen_x, screen_y, view_x, view_y, js, user, session, uid
             ) VALUES (
                CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?
             )',
            $page, $ip, $this->uaAgent, $this->uaName, $this->uaType, $this->uaVersion, $this->uaPlatform,
            $ref, $ref_md5, $ref_type, $sx, $sy, $vx, $vy, $js, $user, $session, $this->uid
        );

        if ($ref_md5) {
            $this->db->exec(
                'INSERT OR IGNORE INTO refseen (
                    ref_md5, dt
                 ) VALUES (
                    ?, CURRENT_TIMESTAMP
                 )',
                $ref_md5
            );
        }

        // log group access
        if (isset($USERINFO['grps'])) {
            $this->logGroups('view', $USERINFO['grps']);
        }

        // resolve the IP
        $this->logIp(clientIP(true));
    }

    /**
     * Log access to a media file
     *
     * Called from action.php
     *
     * @param string $media The media ID
     * @param string $mime The media's mime type
     * @param bool $inline Is this displayed inline?
     * @param int $size Size of the media file
     */
    public function logMedia(string $media, string $mime, bool $inline, int $size): void
    {
        global $INPUT;

        [$mime1, $mime2] = explode('/', strtolower($mime));
        $inline = $inline ? 1 : 0;
        $size = (int)$size;

        $ip = clientIP(true);
        $user = $INPUT->server->str('REMOTE_USER');
        $session = $this->getSession();

        $this->db->exec(
            'INSERT INTO media (
                dt, media, ip, ua, ua_info, ua_type, ua_ver, os, user, session, uid,
                size, mime1, mime2, inline
             ) VALUES (
                CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?
             )',
            $media, $ip, $this->uaAgent, $this->uaName, $this->uaType, $this->uaVersion, $this->uaPlatform,
            $user, $session, $this->uid, $size, $mime1, $mime2, $inline
        );
    }

    /**
     * Log page edits
     *
     * @param string $page The page that was edited
     * @param string $type The type of edit (create, edit, etc.)
     */
    public function logEdit(string $page, string $type): void
    {
        global $INPUT, $USERINFO;

        $ip = clientIP(true);
        $user = $INPUT->server->str('REMOTE_USER');
        $session = $this->getSession();

        $this->db->exec(
            'INSERT INTO edits (
                dt, page, type, ip, user, session, uid
             ) VALUES (
                CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?
             )',
            $page, $type, $ip, $user, $session, $this->uid
        );

        // log group access
        if (isset($USERINFO['grps'])) {
            $this->logGroups('edit', $USERINFO['grps']);
        }
    }

    /**
     * Log login/logoffs and user creations
     *
     * @param string $type The type of login event (login, logout, create)
     * @param string $user The username (optional, will use current user if empty)
     */
    public function logLogin(string $type, string $user = ''): void
    {
        global $INPUT;

        if (!$user) $user = $INPUT->server->str('REMOTE_USER');

        $ip = clientIP(true);
        $session = $this->getSession();

        $this->db->exec(
            'INSERT INTO logins (
                dt, type, ip, user, session, uid
             ) VALUES (
                CURRENT_TIMESTAMP, ?, ?, ?, ?, ?
             )',
            $type, $ip, $user, $session, $this->uid
        );
    }

    /**
     * Log the current page count and size as today's history entry
     */
    public function logHistoryPages(): void
    {
        global $conf;

        // use the popularity plugin's search method to find the wanted data
        /** @var helper_plugin_popularity $pop */
        $pop = plugin_load('helper', 'popularity');
        $list = $this->initEmptySearchList();
        search($list, $conf['datadir'], [$pop, 'searchCountCallback'], ['all' => false], '');
        $page_count = $list['file_count'];
        $page_size = $list['file_size'];

        $this->db->exec(
            'INSERT OR REPLACE INTO history (
                info, value, dt
             ) VALUES (
                ?, ?, CURRENT_TIMESTAMP
             )',
            'page_count', $page_count
        );
        $this->db->exec(
            'INSERT OR REPLACE INTO history (
                info, value, dt
             ) VALUES (
                ?, ?, CURRENT_TIMESTAMP
             )',
            'page_size', $page_size
        );
    }

    /**
     * Log the current media count and size as today's history entry
     */
    public function logHistoryMedia(): void
    {
        global $conf;

        // use the popularity plugin's search method to find the wanted data
        /** @var helper_plugin_popularity $pop */
        $pop = plugin_load('helper', 'popularity');
        $list = $this->initEmptySearchList();
        search($list, $conf['mediadir'], [$pop, 'searchCountCallback'], ['all' => true], '');
        $media_count = $list['file_count'];
        $media_size = $list['file_size'];

        $this->db->exec(
            'INSERT OR REPLACE INTO history (
                info, value, dt
             ) VALUES (
                ?, ?, CURRENT_TIMESTAMP
             )',
            'media_count', $media_count
        );
        $this->db->exec(
            'INSERT OR REPLACE INTO history (
                info, value, dt
             ) VALUES (
                ?, ?, CURRENT_TIMESTAMP
             )',
            'media_size', $media_size
        );
    }

    /**
     * @todo can be dropped in favor of helper_plugin_popularity::initEmptySearchList() once it's public
     * @return array
     */
    protected function initEmptySearchList()
    {
        return array_fill_keys([
            'file_count',
            'file_size',
            'file_max',
            'file_min',
            'dir_count',
            'dir_nest',
            'file_oldest'
        ], 0);
    }
}
