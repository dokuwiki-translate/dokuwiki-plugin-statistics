<?php

namespace dokuwiki\plugin\statistics;


use dokuwiki\plugin\sqlite\SQLiteDB;
use helper_plugin_statistics;

/**
 * This class defines a bunch of SQL queries to fetch various statistics from the database
 */
class Query
{
    protected helper_plugin_statistics $hlp;
    protected SQLiteDB $db;
    protected string $from;
    protected string $to;
    protected string $limit = '';

    /**
     * @param helper_plugin_statistics $hlp
     */
    public function __construct(helper_plugin_statistics $hlp)
    {
        $this->hlp = $hlp;
        $this->db = $hlp->getDB();
        $today = date('Y-m-d');
        $this->setTimeFrame($today, $today);
        $this->setPagination(0, 20);
    }

    /**
     * Set the time frame for all queries
     *
     * @param string $from The start date as YYYY-MM-DD
     * @param string $to The end date as YYYY-MM-DD
     */
    public function setTimeFrame(string $from, string $to): void
    {
        try {
            $from = new \DateTime($from);
            $to = new \DateTime($to);
        } catch (\Exception $e) {
            $from = new \DateTime();
            $to = new \DateTime();
        }
        $from->setTime(0, 0);
        $to->setTime(23, 59, 59);

        $this->from = $from->format('Y-m-d H:i:s');
        $this->to = $to->format('Y-m-d H:i:s');
    }

    /**
     * Set the pagination settings for some queries
     *
     * @param int $start The start offset
     * @param int $limit The number of results. If one more is returned, there is another page
     * @return void
     */
    public function setPagination(int $start, int $limit)
    {
        // when a limit is set, one more is fetched to indicate when a next page exists
        if ($limit) $limit += 1;

        if ($limit) {
            $this->limit = " LIMIT $start,$limit";
        } elseif ($start) {
            $this->limit = " OFFSET $start";
        }
    }

    /**
     * Return some aggregated statistics
     */
    public function aggregate(): array
    {
        $data = [];

        $sql = "SELECT ref_type, COUNT(*) as cnt
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY ref_type";
        $result = $this->db->queryAll($sql, [$this->from, $this->to, 'browser']);

        foreach ($result as $row) {
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
        $result = $this->db->queryRecord($sql, [$this->from, $this->to, 'browser']);

        $data['users'] = max($result['users'] - 1, 0); // subtract empty user
        $data['sessions'] = $result['sessions'];
        $data['pageviews'] = $result['views'];
        $data['visitors'] = $result['visitors'];

        // calculate bounce rate
        if ($data['sessions']) {
            $sql = "SELECT COUNT(*) as cnt
                      FROM session as A
                     WHERE A.dt >= ? AND A.dt <= ?
                       AND views = ?";
            $count = $this->db->queryValue($sql, [$this->from, $this->to, 1]);
            $data['bouncerate'] = $count * 100 / $data['sessions'];
            $data['newvisitors'] = $count * 100 / $data['sessions'];
        }

        // calculate avg. number of views per session
        $sql = "SELECT AVG(views) as cnt
                  FROM session as A
                 WHERE A.dt >= ? AND A.dt <= ?";
        $data['avgpages'] = $this->db->queryValue($sql, [$this->from, $this->to]);

        // average time spent on the site
        $sql = "SELECT AVG(end - dt)/60 as time
                  FROM session as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND dt != end
                   AND DATE(dt) = DATE(end)";
        $data['timespent'] = $this->db->queryValue($sql, [$this->from, $this->to]);

        // logins
        $sql = "SELECT COUNT(*) as logins
                  FROM logins as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND (type = ? OR type = ?)";
        $data['logins'] = $this->db->queryValue($sql, [$this->from, $this->to, 'l', 'p']);

        // registrations
        $sql = "SELECT COUNT(*) as registrations
                  FROM logins as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND type = ?";
        $data['registrations'] = $this->db->queryValue($sql, [$this->from, $this->to, 'C']);

        // current users
        $sql = "SELECT COUNT(*) as current
                  FROM lastseen
                 WHERE dt >= datetime('now', '-10 minutes')";
        $data['current'] = $this->db->queryValue($sql);

        return $data;
    }


    /**
     * Return some trend data about visits and edits in the wiki
     *
     * @param bool $hours Use hour resolution rather than days
     * @return array
     */
    public function dashboardviews(bool $hours = false): array
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
        $result = $this->db->queryAll($sql, [$this->from, $this->to, 'browser']);
        foreach ($result as $row) {
            $data[$row['time']]['sessions'] = $row['sessions'];
            $data[$row['time']]['pageviews'] = $row['pageviews'];
            $data[$row['time']]['visitors'] = $row['visitors'];
        }
        return $data;
    }

    /**
     * @param bool $hours Use hour resolution rather than days
     * @return array
     */
    public function dashboardwiki(bool $hours = false): array
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
            $result = $this->db->queryAll($sql, [$this->from, $this->to, $type]);
            foreach ($result as $row) {
                $data[$row['time']][$type] = $row['cnt'];
            }
        }
        ksort($data);
        return $data;
    }

    /**
     * @param string $info Which type of history to select (FIXME which ones are there?)
     * @param string $interval Group data by this interval (days, weeks, months)
     * @return array
     */
    public function history(string $info, string $interval = 'day'): array
    {
        if ($interval == 'weeks') {
            $TIME = 'strftime(\'%Y\', dt), strftime(\'%W\', dt)';
        } elseif ($interval == 'months') {
            $TIME = 'strftime(\'%Y-%m\', dt)';
        } else {
            $TIME = 'dt'; // FIXME
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
        return $this->db->queryAll($sql, [$this->from, $this->to, $info]);
    }

    /**
     * @return array
     */
    public function searchengines(): array
    {
        $sql = "SELECT COUNT(*) as cnt, engine as eflag, engine
                  FROM search as A
                 WHERE A.dt >= ? AND A.dt <= ?
              GROUP BY engine
              ORDER BY cnt DESC, engine" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to]);
    }

    /**
     * @param bool $extern Limit results to external search engine (true) or dokuwiki (false)
     * @return array
     */
    public function searchphrases(bool $extern): array
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
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, $engineParam]);
    }

    /**
     * @param bool $extern Limit results to external search engine (true) or dokuwiki (false)
     * @return array
     */
    public function searchwords(bool $extern): array
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
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, $engineParam]);
    }

    /**
     * @return array
     */
    public function outlinks(): array
    {
        $sql = "SELECT COUNT(*) as cnt, link as url
                  FROM outlinks as A
                 WHERE A.dt >= ? AND A.dt <= ?
              GROUP BY link
              ORDER BY cnt DESC, link" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to]);
    }

    /**
     * @return array
     */
    public function pages(): array
    {
        $sql = "SELECT COUNT(*) as cnt, page
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY page
              ORDER BY cnt DESC, page" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser']);
    }

    /**
     * @return array
     */
    public function edits(): array
    {
        $sql = "SELECT COUNT(*) as cnt, page
                  FROM edits as A
                 WHERE A.dt >= ? AND A.dt <= ?
              GROUP BY page
              ORDER BY cnt DESC, page" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to]);
    }

    /**
     * @return array
     */
    public function images(): array
    {
        $sql = "SELECT COUNT(*) as cnt, media, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 = ?
              GROUP BY media
              ORDER BY cnt DESC, media" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    /**
     * @return array
     */
    public function imagessum(): array
    {
        $sql = "SELECT COUNT(*) as cnt, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 = ?";
        return $this->db->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    /**
     * @return array
     */
    public function downloads(): array
    {
        $sql = "SELECT COUNT(*) as cnt, media, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 != ?
              GROUP BY media
              ORDER BY cnt DESC, media" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    /**
     * @return array
     */
    public function downloadssum(): array
    {
        $sql = "SELECT COUNT(*) as cnt, SUM(size) as filesize
                  FROM media as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND mime1 != ?";
        return $this->db->queryAll($sql, [$this->from, $this->to, 'image']);
    }

    /**
     * @return array
     */
    public function referer(): array
    {
        $sql = "SELECT COUNT(*) as cnt, ref as url
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
                   AND ref_type = ?
              GROUP BY ref_md5
              ORDER BY cnt DESC, url" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser', 'external']);
    }

    /**
     * @return array
     */
    public function newreferer(): array
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
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser', 'external']);
    }

    /**
     * @return array
     */
    public function countries(): array
    {
        $sql = "SELECT COUNT(DISTINCT session) as cnt, B.code AS cflag, B.country
                  FROM access as A,
                       iplocation as B
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND A.ip = B.ip
              GROUP BY B.code
              ORDER BY cnt DESC, B.country" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to]);
    }

    /**
     * @param bool $ext return extended information
     * @return array
     */
    public function browsers(bool $ext = true): array
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
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser']);
    }

    /**
     * @return array
     */
    public function os(): array
    {
        $sql = "SELECT COUNT(DISTINCT session) as cnt, os as osflag, os
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
              GROUP BY os
              ORDER BY cnt DESC, os" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser']);
    }

    /**
     * @return array
     */
    public function topuser(): array
    {
        $sql = "SELECT COUNT(*) as cnt, user
                  FROM access as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND ua_type = ?
                   AND user != ?
              GROUP BY user
              ORDER BY cnt DESC, user" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser', '']);
    }

    /**
     * @return array
     */
    public function topeditor(): array
    {
        $sql = "SELECT COUNT(*) as cnt, user
                  FROM edits as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND user != ?
              GROUP BY user
              ORDER BY cnt DESC, user" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, '']);
    }

    /**
     * @return array
     */
    public function topgroup(): array
    {
        $sql = "SELECT COUNT(*) as cnt, `group`
                  FROM groups as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND `type` = ?
              GROUP BY `group`
              ORDER BY cnt DESC, `group`" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'view']);
    }

    /**
     * @return array
     */
    public function topgroupedit(): array
    {
        $sql = "SELECT COUNT(*) as cnt, `group`
                  FROM groups as A
                 WHERE A.dt >= ? AND A.dt <= ?
                   AND `type` = ?
              GROUP BY `group`
              ORDER BY cnt DESC, `group`" .
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'edit']);
    }


    /**
     * @return array
     */
    public function resolution(): array
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
            $this->limit;
        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser', 0, 0]);
    }

    /**
     * @return array
     */
    public function viewport(): array
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
            $this->limit;

        return $this->db->queryAll($sql, [$this->from, $this->to, 'browser', 0, 0]);
    }

    /**
     * @return array
     */
    public function seenusers(): array
    {
        $sql = "SELECT `user`, `dt`
                  FROM " . $this->hlp->prefix . "lastseen as A
              ORDER BY `dt` DESC" .
            $this->limit;

        return $this->db->queryAll($sql);
    }

}
