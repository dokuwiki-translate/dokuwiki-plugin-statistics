<?php

namespace dokuwiki\plugin\statistics;

use helper_plugin_statistics;

class Query
{
    protected $hlp;
    protected $from;
    protected $to;

    public function __construct(helper_plugin_statistics $hlp)
    {
        $this->hlp = $hlp;
        $today = date('Y-m-d');
        $this->setTimeFrame($today, $today);
    }

    /**
     * Set the time frame for all queries
     */
    public function setTimeFrame($from, $to)
    {
        // fixme add better sanity checking here:
        $from = preg_replace('/[^\d\-]+/', '', $from);
        $to = preg_replace('/[^\d\-]+/', '', $to);
        if (!$from) $from = date('Y-m-d');
        if (!$to) $to = date('Y-m-d');

        $this->from = $from. ' 00:00:00';
        $this->to = $to. ' 23:59:59';
    }

    /**
     * Return some aggregated statistics
     */
    public function aggregate()
    {
        $data = [];

        $sql = "SELECT ref_type, COUNT(*) as cnt
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY ref_type";
        $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser']);

        if (is_array($result)) foreach ($result as $row) {
            if ($row['ref_type'] == 'search') $data['search'] = $row['cnt'];
            if ($row['ref_type'] == 'external') $data['external'] = $row['cnt'];
            if ($row['ref_type'] == 'internal') $data['internal'] = $row['cnt'];
            if ($row['ref_type'] == '') $data['direct'] = $row['cnt'];
        }

        // general user and session info
        $sql = "SELECT COUNT(DISTINCT session) as sessions,
                       COUNT(session) as views,
                       COUNT(DISTINCT user) as users,
                       COUNT(DISTINCT uid) as visitors
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?";
        $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser']);

        $data['users'] = max($result[0]['users'] - 1, 0); // subtract empty user
        $data['sessions'] = $result[0]['sessions'];
        $data['pageviews'] = $result[0]['views'];
        $data['visitors'] = $result[0]['visitors'];

        // calculate bounce rate
        if ($data['sessions']) {
            $sql = "SELECT COUNT(*) as cnt
                      FROM session as A
                     WHERE A.dt >= ? AND A.dt <= ?
                       AND views = ?";
            $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 1]);
            $data['bouncerate'] = $result[0]['cnt'] * 100 / $data['sessions'];
            $data['newvisitors'] = $result[0]['cnt'] * 100 / $data['sessions'];
        }

        // calculate avg. number of views per session
        $sql = "SELECT AVG(views) as cnt
                  FROM session as A
                 WHERE A.dt >= ? AND A.dt <= ?";
        $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to]);
        $data['avgpages'] = $result[0]['cnt'];

        /* not used currently
                $sql = "SELECT COUNT(id) as robots
                          FROM ".$this->hlp->prefix."access as A
                         WHERE $tlimit
                           AND ua_type = 'robot'";
                $result = $this->hlp->runSQL($sql);
                $data['robots'] = $result[0]['robots'];
        */

        // average time spent on the site
        $sql = "SELECT AVG(end - dt)/60 as time
                  FROM session as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND dt != end
                   AND DATE(dt) = DATE(end)";
        $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to]);
        $data['timespent'] = $result[0]['time'];

        // logins
        $sql = "SELECT COUNT(*) as logins
                  FROM logins as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND (type = ? OR type = ?)";
        $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'l', 'p']);
        $data['logins'] = $result[0]['logins'];

        // registrations
        $sql = "SELECT COUNT(*) as registrations
                  FROM logins as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND type = ?";
        $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'C']);
        $data['registrations'] = $result[0]['registrations'];

        // current users
        $sql = "SELECT COUNT(*) as current
                  FROM lastseen
                 WHERE dt >= datetime('now', '-10 minutes')";
        $result = $this->hlp->getDB()->queryAll($sql);
        $data['current'] = $result[0]['current'];

        return $data;
    }

    /**
     * standard statistics follow, only accesses made by browsers are counted
     * for general stats like browser or OS only visitors not pageviews are counted
     */

    /**
     * Return some trend data about visits and edits in the wiki
     */
    public function dashboardviews($hours = false)
    {
        if ($hours) {
            $TIME = 'strftime(\'%H\', dt)';
        } else {
            $TIME = 'DATE(dt)';
        }

        $data = [];

        // access trends
        $sql = "SELECT $TIME as time,
                       COUNT(DISTINCT session) as sessions,
                       COUNT(session) as pageviews,
                       COUNT(DISTINCT uid) as visitors
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY $TIME
              ORDER BY time";
        $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser']);
        foreach ($result as $row) {
            $data[$row['time']]['sessions'] = $row['sessions'];
            $data[$row['time']]['pageviews'] = $row['pageviews'];
            $data[$row['time']]['visitors'] = $row['visitors'];
        }
        return $data;
    }

    public function dashboardwiki($hours = false)
    {
        if ($hours) {
            $TIME = 'strftime(\'%H\', dt)';
        } else {
            $TIME = 'DATE(dt)';
        }

        $data = [];

        // edit trends
        foreach (['E', 'C', 'D'] as $type) {
            $sql = "SELECT $TIME as time,
                           COUNT(*) as cnt
                      FROM edits as A
                     WHERE A.dt >= ? AND A.dt <= ?
                       AND type = ?
                  GROUP BY $TIME
                  ORDER BY time";
            $result = $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, $type]);
            foreach ($result as $row) {
                $data[$row['time']][$type] = $row['cnt'];
            }
        }
        ksort($data);
        return $data;
    }

    public function history($info, $interval = false)
    {
        if ($interval == 'weeks') {
            $TIME = 'strftime(\'%Y\', dt), strftime(\'%W\', dt)';
        } elseif ($interval == 'months') {
            $TIME = 'strftime(\'%Y-%m\', dt)';
        } else {
            $TIME = 'dt';
        }

        $mod = 1;
        if ($info == 'media_size' || $info == 'page_size') {
            $mod = 1024 * 1024;
        }

        $sql = "SELECT $TIME as time,
                       AVG(value)/$mod as cnt
                  FROM history as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND info = ?
                  GROUP BY $TIME
                  ORDER BY $TIME";
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, $info]);
    }

    public function searchengines($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, engine as eflag, engine
                  FROM search as A
                 WHERE A.dt >= ? AND A.dt <= ?
              GROUP BY engine
              ORDER BY cnt DESC, engine" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to]);
    }

    public function searchphrases($extern, $start = 0, $limit = 20)
    {
        if ($extern) {
            $WHERE = "engine != ?";
            $engineParam = 'dokuwiki';
            $I = '';
        } else {
            $WHERE = "engine = ?";
            $engineParam = 'dokuwiki';
            $I = 'i';
        }
        $sql = "SELECT COUNT(*) as cnt, query, query as ${I}lookup
                  FROM search as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND $WHERE
              GROUP BY query
              ORDER BY cnt DESC, query" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, $engineParam]);
    }

    public function searchwords($extern, $start = 0, $limit = 20)
    {
        if ($extern) {
            $WHERE = "engine != ?";
            $engineParam = 'dokuwiki';
            $I = '';
        } else {
            $WHERE = "engine = ?";
            $engineParam = 'dokuwiki';
            $I = 'i';
        }
        $sql = "SELECT COUNT(*) as cnt, word, word as ${I}lookup
                  FROM search as A,
                       searchwords as B
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND A.id = B.sid
                   AND $WHERE
              GROUP BY word
              ORDER BY cnt DESC, word" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, $engineParam]);
    }

    public function outlinks($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, link as url
                  FROM outlinks as A
                 WHERE A.dt >= ? AND A.dt <= ?
              GROUP BY link
              ORDER BY cnt DESC, link" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to]);
    }

    public function pages($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, page
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY page
              ORDER BY cnt DESC, page" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser']);
    }

    public function edits($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, page
                  FROM edits as A
                 WHERE A.dt >= ? AND A.dt <= ?
              GROUP BY page
              ORDER BY cnt DESC, page" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to]);
    }

    public function images($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, media, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 = ?
              GROUP BY media
              ORDER BY cnt DESC, media" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    public function imagessum()
    {
        $sql = "SELECT COUNT(*) as cnt, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 = ?";
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    public function downloads($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, media, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 != ?
              GROUP BY media
              ORDER BY cnt DESC, media" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    public function downloadssum()
    {
        $sql = "SELECT COUNT(*) as cnt, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 != ?";
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    public function referer($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, ref as url
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
                   AND ref_type = ?
              GROUP BY ref_md5
              ORDER BY cnt DESC, url" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser', 'external']);
    }

    public function newreferer($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, ref as url
                  FROM access as B,
                       refseen as A
                 WHERE B.dt >= ? AND B.dt <= ?
                   AND ua_type = ?
                   AND ref_type = ?
                   AND A.ref_md5 = B.ref_md5
              GROUP BY A.ref_md5
              ORDER BY cnt DESC, url" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser', 'external']);
    }

    public function countries($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(DISTINCT session) as cnt, B.code AS cflag, B.country
                  FROM access as A,
                       iplocation as B
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND A.ip = B.ip
              GROUP BY B.code
              ORDER BY cnt DESC, B.country" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to]);
    }

    public function browsers($start = 0, $limit = 20, $ext = true)
    {
        if ($ext) {
            $sel = 'ua_info as bflag, ua_info as browser, ua_ver';
            $grp = 'ua_info, ua_ver';
        } else {
            $grp = 'ua_info';
            $sel = 'ua_info';
        }

        $sql = "SELECT COUNT(DISTINCT session) as cnt, $sel
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY $grp
              ORDER BY cnt DESC, ua_info" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser']);
    }

    public function os($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(DISTINCT session) as cnt, os as osflag, os
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY os
              ORDER BY cnt DESC, os" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser']);
    }

    public function topuser($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, user
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
                   AND user != ?
              GROUP BY user
              ORDER BY cnt DESC, user" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser', '']);
    }

    public function topeditor($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, user
                  FROM edits as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND user != ?
              GROUP BY user
              ORDER BY cnt DESC, user" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, '']);
    }

    public function topgroup($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, `group`
                  FROM groups as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND `type` = ?
              GROUP BY `group`
              ORDER BY cnt DESC, `group`" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'view']);
    }

    public function topgroupedit($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(*) as cnt, `group`
                  FROM groups as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND `type` = ?
              GROUP BY `group`
              ORDER BY cnt DESC, `group`" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'edit']);
    }


    public function resolution($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(DISTINCT uid) as cnt,
                       ROUND(screen_x/100)*100 as res_x,
                       ROUND(screen_y/100)*100 as res_y,
                       (ROUND(screen_x/100)*100 || 'x' || ROUND(screen_y/100)*100) as resolution
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type  = ?
                   AND screen_x != ?
                   AND screen_y != ?
              GROUP BY resolution
              ORDER BY cnt DESC" .
            $this->mklimit($start, $limit);
        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser', 0, 0]);
    }

    public function viewport($start = 0, $limit = 20)
    {
        $sql = "SELECT COUNT(DISTINCT uid) as cnt,
                       ROUND(view_x/100)*100 as res_x,
                       ROUND(view_y/100)*100 as res_y,
                       (ROUND(view_x/100)*100 || 'x' || ROUND(view_y/100)*100) as resolution
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type  = ?
                   AND view_x != ?
                   AND view_y != ?
              GROUP BY resolution
              ORDER BY cnt DESC" .
            $this->mklimit($start, $limit);

        return $this->hlp->getDB()->queryAll($sql, [$this->from, $this->to, 'browser', 0, 0]);
    }

    public function seenusers($start = 0, $limit = 20)
    {
        $sql = "SELECT `user`, `dt`
                  FROM " . $this->hlp->prefix . "lastseen as A
              ORDER BY `dt` DESC" .
            $this->mklimit($start, $limit);

        return $this->hlp->getDB()->queryAll($sql);
    }


    /**
     * Builds a limit clause
     */
    public function mklimit($start, $limit)
    {
        $start = (int)$start;
        $limit = (int)$limit;
        if ($limit) {
            $limit += 1;
            return " LIMIT $start,$limit";
        } elseif ($start) {
            return " OFFSET $start";
        }
        return '';
    }

}
