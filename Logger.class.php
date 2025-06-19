<?php

namespace dokuwiki\plugin\statistics;

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use DeviceDetector\Parser\OperatingSystem;
use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\Utf8\Clean;
use dokuwiki\Utf8\PhpString;
use helper_plugin_popularity;
use helper_plugin_statistics;


class Logger
{
    protected helper_plugin_statistics $hlp;
    protected SQLiteDB $db;

    protected string $uaAgent;
    protected string $uaType = 'browser';
    protected string $uaName;
    protected string $uaVersion;
    protected string $uaPlatform;
    protected string $uid;


    /**
     * Parses browser info and set internal vars
     */
    public function __construct(helper_plugin_statistics $hlp)
    {
        $this->hlp = $hlp;
        $this->db = $this->hlp->getDB();

        $ua = trim($_SERVER['HTTP_USER_AGENT']);

        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_MAJOR);
        $dd = new DeviceDetector($ua); // FIXME we could use client hints, but need to add headers
        $dd->discardBotInformation();
        $dd->parse();

        if ($dd->isBot()) {
            $this->uaType = 'robot';

            // for now ignore bots
            throw new \RuntimeException('Bot detected, not logging');
        }


        $this->uaAgent = $ua;
        $this->uaName = Browser::getBrowserFamily($dd->getClient('name'));
        $this->uaVersion = $dd->getClient('version');
        $this->uaPlatform = OperatingSystem::getOsFamily($dd->getOs('name'));
        $this->uid = $this->getUID();

        if ($dd->isFeedReader()) {
            $this->uaType = 'feedreader';
        }

        $this->logLastseen();
    }

    /**
     * get the unique user ID
     */
    protected function getUID()
    {
        $uid = $_REQUEST['uid'];
        if (!$uid) $uid = get_doku_pref('plgstats', false);
        if (!$uid) $uid = session_id();
        return $uid;
    }

    /**
     * Return the user's session ID
     *
     * This is usually our own managed session, not a PHP session (only in fallback)
     *
     * @return string
     */
    protected function getSession()
    {
        $ses = $_REQUEST['ses'];
        if (!$ses) $ses = get_doku_pref('plgstatsses', false);
        if (!$ses) $ses = session_id();
        return $ses;
    }

    /**
     * Log that we've seen the user (authenticated only)
     *
     * This is called directly from the constructor and thus logs always,
     * regardless from where the log is initiated
     */
    public function logLastseen()
    {
        if (empty($_SERVER['REMOTE_USER'])) return;

        $this->db->exec(
            'REPLACE INTO lastseen (user, dt) VALUES (?, CURRENT_TIMESTAMP)',
            $_SERVER['REMOTE_USER'],
        );
    }

    /**
     * Log actions by groups
     *
     * @param string $type The type of access to log ('view','edit')
     * @param array $groups The groups to log
     */
    public function logGroups($type, $groups)
    {
        if (!is_array($groups)) {
            return;
        }

        $tolog = (array)$this->hlp->getConf('loggroups');
        $groups = array_intersect($groups, $tolog);
        if ($groups === []) {
            return;
        }


        $params = [];
        $sql = "INSERT INTO groups (`type`, `group`) VALUES ";
        foreach ($groups as $group) {
            $sql .= '(?, ?),';
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
     */
    public function log_externalsearch($referer, &$type)
    {
        $referer = PhpString::strtolower($referer);
        include(__DIR__ . '/searchengines.php');
        /** @var array $SEARCHENGINES */

        $query = '';
        $name = '';

        // parse the referer
        $urlparts = parse_url($referer);
        $domain = $urlparts['host'];
        $qpart = $urlparts['query'];
        if (!$qpart) $qpart = $urlparts['fragment']; //google does this

        $params = [];
        parse_str($qpart, $params);

        // check domain against common search engines
        foreach ($SEARCHENGINES as $regex => $info) {
            if (preg_match('/' . $regex . '/', $domain)) {
                $type = 'search';
                $name = array_shift($info);
                // check the known parameters for content
                foreach ($info as $k) {
                    if (empty($params[$k])) continue;
                    $query = $params[$k];
                    break;
                }
                break;
            }
        }

        // try some generic search engin parameters
        if ($type != 'search') foreach (['search', 'query', 'q', 'keywords', 'keyword'] as $k) {
            if (empty($params[$k])) continue;
            $query = $params[$k];
            // we seem to have found some generic search, generate name from domain
            $name = preg_replace('/(\.co)?\.([a-z]{2,5})$/', '', $domain); //strip tld
            $name = explode('.', $name);
            $name = array_pop($name);
            $type = 'search';
            break;
        }

        // still no hit? return
        if ($type != 'search') return;

        // clean the query
        $query = preg_replace('/^(cache|related):[^\+]+/', '', $query); // non-search queries
        $query = preg_replace('/ +/', ' ', $query); // ws compact
        $query = trim($query);
        if (!Clean::isUtf8($query)) $query = utf8_encode($query); // assume latin1 if not utf8

        // no query? no log
        if (!$query) return;

        // log it!
        $words = explode(' ', Clean::stripspecials($query, ' ', '\._\-:\*'));
        $this->log_search($_REQUEST['p'], $query, $words, $name);
    }

    /**
     * The given data to the search related tables
     */
    public function log_search($page, $query, $words, $engine)
    {
        $page = addslashes($page);
        $query = addslashes($query);
        $engine = addslashes($engine);

        $sql = "INSERT INTO " . $this->hlp->prefix . "search
                    SET dt       = NOW(),
                        page     = '$page',
                        query    = '$query',
                        engine   = '$engine'";
        $id = $this->hlp->runSQL($sql);
        if (is_null($id)) return;

        foreach ($words as $word) {
            if (!$word) continue;
            $word = addslashes($word);
            $sql = "INSERT DELAYED INTO " . $this->hlp->prefix . "searchwords
                       SET sid  = $id,
                           word = '$word'";
            $this->hlp->runSQL($sql);
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
    public function log_session($addview = 0)
    {
        // only log browser sessions
        if ($this->uaType != 'browser') return;

        $addview = addslashes($addview);
        $session = addslashes($this->getSession());
        $uid = addslashes($this->uid);
        $sql = "INSERT DELAYED INTO " . $this->hlp->prefix . "session
                   SET session = '$session',
                       dt      = NOW(),
                       end     = NOW(),
                       views   = $addview,
                       uid     = '$uid'
                ON DUPLICATE KEY UPDATE
                       end     = NOW(),
                       views   = views + $addview,
                       uid     = '$uid'";
        $this->hlp->runSQL($sql);
    }

    /**
     * Resolve IP to country/city
     */
    public function log_ip($ip)
    {
        // check if IP already known and up-to-date
        $sql = "SELECT ip
                  FROM " . $this->hlp->prefix . "iplocation
                 WHERE ip ='" . addslashes($ip) . "'
                   AND lastupd > DATE_SUB(CURDATE(),INTERVAL 30 DAY)";
        $result = $this->hlp->runSQL($sql);
        if ($result[0]['ip']) return;

        $http = new DokuHTTPClient();
        $http->timeout = 10;
        $data = $http->get('http://api.hostip.info/get_html.php?ip=' . $ip);

        if (preg_match('/^Country: (.*?) \((.*?)\)\nCity: (.*?)$/s', $data, $match)) {
            $country = addslashes(ucwords(strtolower(trim($match[1]))));
            $code = addslashes(strtolower(trim($match[2])));
            $city = addslashes(ucwords(strtolower(trim($match[3]))));
            $host = addslashes(gethostbyaddr($ip));
            $ip = addslashes($ip);

            $sql = "REPLACE INTO " . $this->hlp->prefix . "iplocation
                        SET ip = '$ip',
                            country = '$country',
                            code    = '$code',
                            city    = '$city',
                            host    = '$host'";
            $this->hlp->runSQL($sql);
        }
    }

    /**
     * log a click on an external link
     *
     * called from log.php
     */
    public function log_outgoing()
    {
        if (!$_REQUEST['ol']) return;

        $link = addslashes($_REQUEST['ol']);
        $link_md5 = md5($link);
        $session = addslashes($this->getSession());
        $page = addslashes($_REQUEST['p']);

        $sql = "INSERT DELAYED INTO " . $this->hlp->prefix . "outlinks
                    SET dt       = NOW(),
                        session  = '$session',
                        page     = '$page',
                        link_md5 = '$link_md5',
                        link     = '$link'";
        $ok = $this->hlp->runSQL($sql);
        if (is_null($ok)) {
            global $MSG;
            print_r($MSG);
        }
    }

    /**
     * log a page access
     *
     * called from log.php
     */
    public function log_access()
    {
        if (!$_REQUEST['p']) return;
        global $USERINFO;

        # FIXME check referer against blacklist and drop logging for bad boys

        // handle referer
        $referer = trim($_REQUEST['r']);
        if ($referer) {
            $ref = addslashes($referer);
            $ref_md5 = ($ref) ? md5($referer) : '';
            if (str_starts_with($referer, DOKU_URL)) {
                $ref_type = 'internal';
            } else {
                $ref_type = 'external';
                $this->log_externalsearch($referer, $ref_type);
            }
        } else {
            $ref = '';
            $ref_md5 = '';
            $ref_type = '';
        }

        // handle user agent
        $ua = addslashes($this->uaAgent);
        $ua_type = addslashes($this->uaType);
        $ua_ver = addslashes($this->uaVersion);
        $os = addslashes($this->uaPlatform);
        $ua_info = addslashes($this->uaName);

        $page = addslashes($_REQUEST['p']);
        $ip = addslashes(clientIP(true));
        $sx = (int)$_REQUEST['sx'];
        $sy = (int)$_REQUEST['sy'];
        $vx = (int)$_REQUEST['vx'];
        $vy = (int)$_REQUEST['vy'];
        $js = (int)$_REQUEST['js'];
        $uid = addslashes($this->uid);
        $user = addslashes($_SERVER['REMOTE_USER']);
        $session = addslashes($this->getSession());

        $sql = "INSERT DELAYED INTO " . $this->hlp->prefix . "access
                    SET dt       = NOW(),
                        page     = '$page',
                        ip       = '$ip',
                        ua       = '$ua',
                        ua_info  = '$ua_info',
                        ua_type  = '$ua_type',
                        ua_ver   = '$ua_ver',
                        os       = '$os',
                        ref      = '$ref',
                        ref_md5  = '$ref_md5',
                        ref_type = '$ref_type',
                        screen_x = '$sx',
                        screen_y = '$sy',
                        view_x   = '$vx',
                        view_y   = '$vy',
                        js       = '$js',
                        user     = '$user',
                        session  = '$session',
                        uid      = '$uid'";
        $ok = $this->hlp->runSQL($sql);
        if (is_null($ok)) {
            global $MSG;
            print_r($MSG);
        }

        $sql = "INSERT DELAYED IGNORE INTO " . $this->hlp->prefix . "refseen
                   SET ref_md5  = '$ref_md5',
                       dt       = NOW()";
        $ok = $this->hlp->runSQL($sql);
        if (is_null($ok)) {
            global $MSG;
            print_r($MSG);
        }

        // log group access
        if (isset($USERINFO['grps'])) {
            $this->logGroups('view', $USERINFO['grps']);
        }

        // resolve the IP
        $this->log_ip(clientIP(true));
    }

    /**
     * Log access to a media file
     *
     * called from action.php
     *
     * @param string $media the media ID
     * @param string $mime the media's mime type
     * @param bool $inline is this displayed inline?
     * @param int $size size of the media file
     */
    public function log_media($media, $mime, $inline, $size)
    {
        // handle user agent
        $ua = addslashes($this->uaAgent);
        $ua_type = addslashes($this->uaType);
        $ua_ver = addslashes($this->uaVersion);
        $os = addslashes($this->uaPlatform);
        $ua_info = addslashes($this->uaName);

        $media = addslashes($media);
        [$mime1, $mime2] = explode('/', strtolower($mime));
        $mime1 = addslashes($mime1);
        $mime2 = addslashes($mime2);
        $inline = $inline ? 1 : 0;
        $size = (int)$size;

        $ip = addslashes(clientIP(true));
        $uid = addslashes($this->uid);
        $user = addslashes($_SERVER['REMOTE_USER']);
        $session = addslashes($this->getSession());

        $sql = "INSERT DELAYED INTO " . $this->hlp->prefix . "media
                    SET dt       = NOW(),
                        media    = '$media',
                        ip       = '$ip',
                        ua       = '$ua',
                        ua_info  = '$ua_info',
                        ua_type  = '$ua_type',
                        ua_ver   = '$ua_ver',
                        os       = '$os',
                        user     = '$user',
                        session  = '$session',
                        uid      = '$uid',
                        size     = $size,
                        mime1    = '$mime1',
                        mime2    = '$mime2',
                        inline   = $inline
                        ";
        $ok = $this->hlp->runSQL($sql);
        if (is_null($ok)) {
            global $MSG;
            dbglog($MSG);
        }
    }

    /**
     * Log edits
     */
    public function log_edit($page, $type)
    {
        global $USERINFO;

        $ip = addslashes(clientIP(true));
        $user = addslashes($_SERVER['REMOTE_USER']);
        $session = addslashes($this->getSession());
        $uid = addslashes($this->uid);
        $page = addslashes($page);
        $type = addslashes($type);

        $sql = "INSERT DELAYED INTO " . $this->hlp->prefix . "edits
                    SET dt       = NOW(),
                        page     = '$page',
                        type     = '$type',
                        ip       = '$ip',
                        user     = '$user',
                        session  = '$session',
                        uid      = '$uid'";
        $this->hlp->runSQL($sql);

        // log group access
        if (isset($USERINFO['grps'])) {
            $this->logGroups('edit', $USERINFO['grps']);
        }
    }

    /**
     * Log login/logoffs and user creations
     */
    public function log_login($type, $user = '')
    {
        if (!$user) $user = $_SERVER['REMOTE_USER'];

        $ip = addslashes(clientIP(true));
        $user = addslashes($user);
        $session = addslashes($this->getSession());
        $uid = addslashes($this->uid);
        $type = addslashes($type);

        $sql = "INSERT DELAYED INTO " . $this->hlp->prefix . "logins
                    SET dt       = NOW(),
                        type     = '$type',
                        ip       = '$ip',
                        user     = '$user',
                        session  = '$session',
                        uid      = '$uid'";
        $this->hlp->runSQL($sql);
    }

    /**
     * Log the current page count and size as today's history entry
     */
    public function log_history_pages()
    {
        global $conf;

        // use the popularity plugin's search method to find the wanted data
        /** @var helper_plugin_popularity $pop */
        $pop = plugin_load('helper', 'popularity');
        $list = [];
        search($list, $conf['datadir'], [$pop, 'searchCountCallback'], ['all' => false], '');
        $page_count = $list['file_count'];
        $page_size = $list['file_size'];

        print_r($list);

        $sql = "REPLACE INTO " . $this->hlp->prefix . "history
                        (`info`, `value`, `dt`)
                        VALUES
                        ( 'page_count', $page_count, DATE(NOW()) ),
                        ( 'page_size',  $page_size, DATE(NOW()) )
                        ";
        $ok = $this->hlp->runSQL($sql);
        if (is_null($ok)) {
            global $MSG;
            print_r($MSG);
        }
    }

    /**
     * Log the current page count and size as today's history entry
     */
    public function log_history_media()
    {
        global $conf;

        // use the popularity plugin's search method to find the wanted data
        /** @var helper_plugin_popularity $pop */
        $pop = plugin_load('helper', 'popularity');
        $list = [];
        search($list, $conf['mediadir'], [$pop, 'searchCountCallback'], ['all' => true], '');
        $media_count = $list['file_count'];
        $media_size = $list['file_size'];

        $sql = "REPLACE INTO " . $this->hlp->prefix . "history
                        (`info`, `value`, `dt`)
                        VALUES
                        ( 'media_count', $media_count, DATE(NOW()) ),
                        ( 'media_size',  $media_size, DATE(NOW()) )
                        ";
        $ok = $this->hlp->runSQL($sql);
        if (is_null($ok)) {
            global $MSG;
            print_r($MSG);
        }
    }
}
