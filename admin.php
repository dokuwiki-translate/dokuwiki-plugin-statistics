<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
use dokuwiki\Extension\AdminPlugin;
use dokuwiki\plugin\statistics\SearchEngines;

/**
 * statistics plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@splitbrain.org>
 */
class admin_plugin_statistics extends AdminPlugin
{
    /** @var string the currently selected page */
    protected $opt = '';

    /** @var string from date in YYYY-MM-DD */
    protected $from = '';
    /** @var string to date in YYYY-MM-DD */
    protected $to = '';
    /** @var int Offset to use when displaying paged data */
    protected $start = 0;

    /** @var helper_plugin_statistics */
    protected $hlp;

    /**
     * Available statistic pages
     */
    protected $pages = [
        'dashboard' => 1,
        'content' => ['page', 'edits', 'images', 'downloads', 'history'],
        'users' => ['topuser', 'topeditor', 'topgroup', 'topgroupedit', 'seenusers'],
        'links' => ['referer', 'newreferer', 'outlinks'],
        'search' => ['searchengines', 'internalsearchphrases', 'internalsearchwords'],
        'technology' => ['browsers', 'os', 'countries', 'resolution', 'viewport']
    ];

    /** @var array keeps a list of all real content pages, generated from above array */
    protected $allowedpages = [];

    /**
     * Initialize the helper
     */
    public function __construct()
    {
        $this->hlp = plugin_load('helper', 'statistics');

        // build a list of pages
        foreach ($this->pages as $key => $val) {
            if (is_array($val)) {
                $this->allowedpages = array_merge($this->allowedpages, $val);
            } else {
                $this->allowedpages[] = $key;
            }
        }
    }

    /**
     * Access for managers allowed
     */
    public function forAdminOnly()
    {
        return false;
    }

    /**
     * return sort order for position in admin menu
     */
    public function getMenuSort()
    {
        return 350;
    }

    /**
     * handle user request
     */
    public function handle()
    {
        global $INPUT;
        $this->opt = preg_replace('/[^a-z]+/', '', $INPUT->str('opt'));
        if (!in_array($this->opt, $this->allowedpages)) $this->opt = 'dashboard';

        $this->start = $INPUT->int('s');
        $this->setTimeframe($INPUT->str('f', date('Y-m-d')), $INPUT->str('t', date('Y-m-d')));
    }

    /**
     * set limit clause
     */
    public function setTimeframe($from, $to)
    {
        // swap if wrong order
        if ($from > $to) [$from, $to] = [$to, $from];

        $this->hlp->getQuery()->setTimeFrame($from, $to);
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * Output the Statistics
     */
    public function html()
    {
        echo '<script src="' . DOKU_BASE . 'lib/plugins/statistics/lib/chart.js"></script>';
        echo '<script src="' . DOKU_BASE . 'lib/plugins/statistics/lib/chartjs-plugin-datalabels.js"></script>';

        echo '<div id="plugin__statistics">';
        echo '<h1>' . $this->getLang('menu') . '</h1>';
        $this->html_timeselect();
        tpl_flush();

        $method = 'html_' . $this->opt;
        if (method_exists($this, $method)) {
            echo '<div class="plg_stats_' . $this->opt . '">';
            echo '<h2>' . $this->getLang($this->opt) . '</h2>';
            $this->$method();
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Return the TOC
     *
     * @return array
     */
    public function getTOC()
    {
        $toc = [];
        foreach ($this->pages as $key => $info) {
            if (is_array($info)) {
                $toc[] = html_mktocitem(
                    '',
                    $this->getLang($key),
                    1,
                    ''
                );

                foreach ($info as $page) {
                    $toc[] = html_mktocitem(
                        '?do=admin&amp;page=statistics&amp;opt=' . $page .
                        '&amp;f=' . $this->from .
                        '&amp;t=' . $this->to,
                        $this->getLang($page),
                        2,
                        ''
                    );
                }
            } else {
                $toc[] = html_mktocitem(
                    '?do=admin&amp;page=statistics&amp;opt=' . $key .
                    '&amp;f=' . $this->from .
                    '&amp;t=' . $this->to,
                    $this->getLang($key),
                    1,
                    ''
                );
            }
        }
        return $toc;
    }

    public function html_graph($name, $width, $height)
    {
        $this->hlp->getGraph($this->from, $this->to, $width, $height)->$name();
    }

    /**
     * Outputs pagination links
     *
     * @param int $limit
     * @param int $next
     */
    public function html_pager($limit, $next)
    {
        $params = [
            'do' => 'admin',
            'page' => 'statistics',
            'opt' => $this->opt,
            'f' => $this->from,
            't' => $this->to,
        ];

        echo '<div class="plg_stats_pager">';
        if ($this->start > 0) {
            $go = max($this->start - $limit, 0);
            $params['s'] = $go;
            echo '<a href="?' . buildURLparams($params) . '" class="prev button">' . $this->getLang('prev') . '</a>';
        }

        if ($next) {
            $go = $this->start + $limit;
            $params['s'] = $go;
            echo '<a href="?' . buildURLparams($params) . '" class="next button">' . $this->getLang('next') . '</a>';
        }
        echo '</div>';
    }

    /**
     * Print the time selection menu
     */
    public function html_timeselect()
    {
        $quick = [
            'today' => date('Y-m-d'),
            'last1' => date('Y-m-d', time() - (60 * 60 * 24)),
            'last7' => date('Y-m-d', time() - (60 * 60 * 24 * 7)),
            'last30' => date('Y-m-d', time() - (60 * 60 * 24 * 30)),
        ];


        echo '<div class="plg_stats_timeselect">';
        echo '<span>' . $this->getLang('time_select') . '</span> ';

        echo '<form action="' . DOKU_SCRIPT . '" method="get">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="statistics" />';
        echo '<input type="hidden" name="opt" value="' . $this->opt . '" />';
        echo '<input type="date" name="f" value="' . $this->from . '" class="edit" />';
        echo '<input type="date" name="t" value="' . $this->to . '" class="edit" />';
        echo '<input type="submit" value="go" class="button" />';
        echo '</form>';

        echo '<ul>';
        foreach ($quick as $name => $time) {
            // today is included only today
            $to = $name == 'today' ? $quick['today'] : $quick['last1'];

            $url = buildURLparams([
                'do' => 'admin',
                'page' => 'statistics',
                'opt' => $this->opt,
                'f' => $time,
                't' => $to,
            ]);

            echo '<li>';
            echo '<a href="?' . $url . '">';
            echo $this->getLang('time_' . $name);
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';

        echo '</div>';
    }

    /**
     * Print an introductionary screen
     */
    public function html_dashboard()
    {
        echo '<p>' . $this->getLang('intro_dashboard') . '</p>';

        // general info
        echo '<div class="plg_stats_top">';
        $result = $this->hlp->getQuery()->aggregate();

        echo '<ul>';
        foreach (['pageviews', 'sessions', 'visitors', 'users', 'logins', 'current'] as $name) {
            echo '<li><div class="li">' . sprintf($this->getLang('dash_' . $name), $result[$name]) . '</div></li>';
        }
        echo '</ul>';

        echo '<ul>';
        foreach (['bouncerate', 'timespent', 'avgpages', 'newvisitors', 'registrations'] as $name) {
            echo '<li><div class="li">' . sprintf($this->getLang('dash_' . $name), $result[$name]) . '</div></li>';
        }
        echo '</ul>';

        $this->html_graph('dashboardviews', 700, 280);
        $this->html_graph('dashboardwiki', 700, 280);
        echo '</div>';

        $quickgraphs = [
            ['lbl' => 'dash_mostpopular', 'query' => 'pages', 'opt' => 'page'],
            ['lbl' => 'dash_newincoming', 'query' => 'newreferer', 'opt' => 'newreferer'],
            ['lbl' => 'dash_topsearch', 'query' => 'searchphrases', 'opt' => 'internalsearchphrases'],
        ];

        foreach ($quickgraphs as $graph) {
            $params = [
                'do' => 'admin',
                'page' => 'statistics',
                'f' => $this->from,
                't' => $this->to,
                'opt' => $graph['opt'],
            ];

            echo '<div>';
            echo '<h2>' . $this->getLang($graph['lbl']) . '</h2>';
            $result = call_user_func([$this->hlp->getQuery(), $graph['query']]);
            $this->html_resulttable($result);
            echo '<p><a href="?' . buildURLparams($params) . '" class="more">' . $this->getLang('more') . '…</a></p>';
            echo '</div>';
        }
    }

    public function html_history()
    {
        echo '<p>' . $this->getLang('intro_history') . '</p>';
        $this->html_graph('history_page_count', 600, 200);
        $this->html_graph('history_page_size', 600, 200);
        $this->html_graph('history_media_count', 600, 200);
        $this->html_graph('history_media_size', 600, 200);
    }

    public function html_countries()
    {
        echo '<p>' . $this->getLang('intro_countries') . '</p>';
        $this->html_graph('countries', 300, 300);
        $result = $this->hlp->getQuery()->countries();
        $this->html_resulttable($result, '', 150);
    }

    public function html_page()
    {
        echo '<p>' . $this->getLang('intro_page') . '</p>';
        $result = $this->hlp->getQuery()->pages();
        $this->html_resulttable($result, '', 150);
    }

    public function html_edits()
    {
        echo '<p>' . $this->getLang('intro_edits') . '</p>';
        $result = $this->hlp->getQuery()->edits();
        $this->html_resulttable($result, '', 150);
    }

    public function html_images()
    {
        echo '<p>' . $this->getLang('intro_images') . '</p>';

        $result = $this->hlp->getQuery()->imagessum();
        echo '<p>';
        echo sprintf($this->getLang('trafficsum'), $result[0]['cnt'], filesize_h($result[0]['filesize']));
        echo '</p>';

        $result = $this->hlp->getQuery()->images();
        $this->html_resulttable($result, '', 150);
    }

    public function html_downloads()
    {
        echo '<p>' . $this->getLang('intro_downloads') . '</p>';

        $result = $this->hlp->getQuery()->downloadssum();
        echo '<p>';
        echo sprintf($this->getLang('trafficsum'), $result[0]['cnt'], filesize_h($result[0]['filesize']));
        echo '</p>';

        $result = $this->hlp->getQuery()->downloads();
        $this->html_resulttable($result, '', 150);
    }

    public function html_browsers()
    {
        echo '<p>' . $this->getLang('intro_browsers') . '</p>';
        $this->html_graph('browsers', 300, 300);
        $result = $this->hlp->getQuery()->browsers(false);
        $this->html_resulttable($result, '', 150);
    }

    public function html_topuser()
    {
        echo '<p>' . $this->getLang('intro_topuser') . '</p>';
        $this->html_graph('topuser', 300, 300);
        $result = $this->hlp->getQuery()->topuser();
        $this->html_resulttable($result, '', 150);
    }

    public function html_topeditor()
    {
        echo '<p>' . $this->getLang('intro_topeditor') . '</p>';
        $this->html_graph('topeditor', 300, 300);
        $result = $this->hlp->getQuery()->topeditor();
        $this->html_resulttable($result, '', 150);
    }

    public function html_topgroup()
    {
        echo '<p>' . $this->getLang('intro_topgroup') . '</p>';
        $this->html_graph('topgroup', 300, 300);
        $result = $this->hlp->getQuery()->topgroup();
        $this->html_resulttable($result, '', 150);
    }

    public function html_topgroupedit()
    {
        echo '<p>' . $this->getLang('intro_topgroupedit') . '</p>';
        $this->html_graph('topgroupedit', 300, 300);
        $result = $this->hlp->getQuery()->topgroupedit();
        $this->html_resulttable($result, '', 150);
    }

    public function html_os()
    {
        echo '<p>' . $this->getLang('intro_os') . '</p>';
        $this->html_graph('os', 300, 300);
        $result = $this->hlp->getQuery()->os();
        $this->html_resulttable($result, '', 150);
    }

    public function html_referer()
    {
        $result = $this->hlp->getQuery()->aggregate();

        if ($result['referers']) {
            printf(
                '<p>' . $this->getLang('intro_referer') . '</p>',
                $result['referers'],
                $result['direct'],
                (100 * $result['direct'] / $result['referers']),
                $result['search'],
                (100 * $result['search'] / $result['referers']),
                $result['external'],
                (100 * $result['external'] / $result['referers'])
            );
        }

        $result = $this->hlp->getQuery()->referer();
        $this->html_resulttable($result, '', 150);
    }

    public function html_newreferer()
    {
        echo '<p>' . $this->getLang('intro_newreferer') . '</p>';

        $result = $this->hlp->getQuery()->newreferer();
        $this->html_resulttable($result, '', 150);
    }

    public function html_outlinks()
    {
        echo '<p>' . $this->getLang('intro_outlinks') . '</p>';
        $result = $this->hlp->getQuery()->outlinks();
        $this->html_resulttable($result, '', 150);
    }

    public function html_searchphrases()
    {
        echo '<p>' . $this->getLang('intro_searchphrases') . '</p>';
        $result = $this->hlp->getQuery()->searchphrases(true);
        $this->html_resulttable($result, '', 150);
    }

    public function html_searchwords()
    {
        echo '<p>' . $this->getLang('intro_searchwords') . '</p>';
        $result = $this->hlp->getQuery()->searchwords(true);
        $this->html_resulttable($result, '', 150);
    }

    public function html_internalsearchphrases()
    {
        echo '<p>' . $this->getLang('intro_internalsearchphrases') . '</p>';
        $result = $this->hlp->getQuery()->searchphrases(false);
        $this->html_resulttable($result, '', 150);
    }

    public function html_internalsearchwords()
    {
        echo '<p>' . $this->getLang('intro_internalsearchwords') . '</p>';
        $result = $this->hlp->getQuery()->searchwords(false);
        $this->html_resulttable($result, '', 150);
    }

    public function html_searchengines()
    {
        echo '<p>' . $this->getLang('intro_searchengines') . '</p>';
        $this->html_graph('searchengines', 400, 200);
        $result = $this->hlp->getQuery()->searchengines();
        $this->html_resulttable($result, '', 150);
    }

    public function html_resolution()
    {
        echo '<p>' . $this->getLang('intro_resolution') . '</p>';
        $this->html_graph('resolution', 650, 490);
        $result = $this->hlp->getQuery()->resolution();
        $this->html_resulttable($result, '', 150);
    }

    public function html_viewport()
    {
        echo '<p>' . $this->getLang('intro_viewport') . '</p>';
        $this->html_graph('viewport', 650, 490);
        $result = $this->hlp->getQuery()->viewport();
        $this->html_resulttable($result, '', 150);
    }

    public function html_seenusers()
    {
        echo '<p>' . $this->getLang('intro_seenusers') . '</p>';
        $result = $this->hlp->getQuery()->seenusers();
        $this->html_resulttable($result, '', 150);
    }

    /**
     * Display a result in a HTML table
     */
    public function html_resulttable($result, $header = '', $pager = 0)
    {
        echo '<table class="inline">';
        if (is_array($header)) {
            echo '<tr>';
            foreach ($header as $h) {
                echo '<th>' . hsc($h) . '</th>';
            }
            echo '</tr>';
        }

        $count = 0;
        if (is_array($result)) foreach ($result as $row) {
            echo '<tr>';
            foreach ($row as $k => $v) {
                if ($k == 'res_x') continue;
                if ($k == 'res_y') continue;

                echo '<td class="plg_stats_X' . $k . '">';
                if ($k == 'page') {
                    echo '<a href="' . wl($v) . '" class="wikilink1">';
                    echo hsc($v);
                    echo '</a>';
                } elseif ($k == 'media') {
                    echo '<a href="' . ml($v) . '" class="wikilink1">';
                    echo hsc($v);
                    echo '</a>';
                } elseif ($k == 'filesize') {
                    echo filesize_h($v);
                } elseif ($k == 'url') {
                    $url = hsc($v);
                    $url = preg_replace('/^https?:\/\/(www\.)?/', '', $url);
                    if (strlen($url) > 45) {
                        $url = substr($url, 0, 30) . ' &hellip; ' . substr($url, -15);
                    }
                    echo '<a href="' . $v . '" class="urlextern">';
                    echo $url;
                    echo '</a>';
                } elseif ($k == 'ilookup') {
                    echo '<a href="' . wl('', ['id' => $v, 'do' => 'search']) . '">Search</a>';
                } elseif ($k == 'lookup') {
                    echo '<a href="http://www.google.com/search?q=' . rawurlencode($v) . '">';
                    echo '<img src="' . DOKU_BASE . 'lib/plugins/statistics/ico/search/google.png" alt="Google" />';
                    echo '</a> ';

                    echo '<a href="http://search.yahoo.com/search?p=' . rawurlencode($v) . '">';
                    echo '<img src="' . DOKU_BASE . 'lib/plugins/statistics/ico/search/yahoo.png" alt="Yahoo!" />';
                    echo '</a> ';

                    echo '<a href="http://www.bing.com/search?q=' . rawurlencode($v) . '">';
                    echo '<img src="' . DOKU_BASE . 'lib/plugins/statistics/ico/search/bing.png" alt="Bing" />';
                    echo '</a> ';
                } elseif ($k == 'engine') {
                    $name = SearchEngines::getName($v);
                    $url = SearchEngines::getURL($v);
                    if ($url) {
                        echo '<a href="' . $url . '">' . hsc($name) . '</a>';
                    } else {
                        echo hsc($name);
                    }
                } elseif ($k == 'html') {
                    echo $v;
                } else {
                    echo hsc($v);
                }
                echo '</td>';
            }
            echo '</tr>';

            if ($pager && ($count == $pager)) break;
            $count++;
        }
        echo '</table>';

        if ($pager) $this->html_pager($pager, count($result) > $pager);
    }
}
