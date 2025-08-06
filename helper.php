<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\plugin\statistics\Logger;
use dokuwiki\plugin\statistics\Query;
use dokuwiki\plugin\statistics\StatisticsGraph;

/**
 * Statistics Plugin
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Andreas Gohr <andi@splitbrain.org>
 */
class helper_plugin_statistics extends Plugin
{
    public $prefix;
    protected $oQuery;
    protected ?Logger $oLogger = null;
    protected $oGraph;
    protected ?SQLiteDB $db = null;

    /**
     * Get SQLiteDB instance
     *
     * @return SQLiteDB|null
     * @throws Exception when SQLite initialization failed
     */
    public function getDB(): ?SQLiteDB
    {
        if (!$this->db instanceof SQLiteDB) {
            if (!class_exists(SQLiteDB::class)) throw new Exception('SQLite Plugin missing');
            $this->db = new SQLiteDB('statistics', DOKU_PLUGIN . 'statistics/db/');
        }
        return $this->db;
    }


    /**
     * Return an instance of the query class
     *
     * @return Query
     */
    public function getQuery(): Query
    {
        if (is_null($this->oQuery)) {
            $this->oQuery = new Query($this);
        }
        return $this->oQuery;
    }

    /**
     * Return an instance of the logger class
     *
     * @return Logger
     */
    public function getLogger(): ?Logger
    {
        if (is_null($this->oLogger)) {
            $this->oLogger = new Logger($this);
        }
        return $this->oLogger;
    }

    /**
     * Return an instance of the Graph class
     *
     * @return StatisticsGraph
     */
    public function getGraph($from, $to, $width, $height)
    {
        if (is_null($this->oGraph)) {
            $this->oGraph = new StatisticsGraph($this, $from, $to, $width, $height);
        }
        return $this->oGraph;
    }

    /**
     * Just send a 1x1 pixel blank gif to the browser
     *
     * @called from log.php
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Harry Fuecks <fuecks@gmail.com>
     */
    public function sendGIF($transparent = true)
    {
        if ($transparent) {
            $img = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7');
        } else {
            $img = base64_decode('R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=');
        }
        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($img));
        header('Connection: Close');
        echo $img;
        flush();
        // Browser should drop connection after this
        // Thinks it got the whole image
    }
}
