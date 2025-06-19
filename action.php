<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_statistics extends ActionPlugin
{
    /**
     * register the eventhandlers and initialize some options
     */
    public function register(EventHandler $controller)
    {
        global $JSINFO;
        global $ACT;
        $JSINFO['act'] = $ACT;

        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'logedits', []);
        $controller->register_hook('SEARCH_QUERY_FULLPAGE', 'AFTER', $this, 'logsearch', []);
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'loglogins', []);
        $controller->register_hook('AUTH_USER_CHANGE', 'AFTER', $this, 'logregistration', []);
        $controller->register_hook('FETCH_MEDIA_STATUS', 'BEFORE', $this, 'logmedia', []);
        $controller->register_hook('INDEXER_TASKS_RUN', 'AFTER', $this, 'loghistory', []);
    }

    /**
     * @fixme call this in the webbug call
     */
    public function putpixel()
    {
        global $ID, $INPUT;
        $url = DOKU_BASE . 'lib/plugins/statistics/log.php?p=' . rawurlencode($ID) .
            '&amp;r=' . rawurlencode($INPUT->server->str('HTTP_REFERER')) . '&rnd=' . time();

        echo '<noscript><img alt="" src="' . $url . '" width="1" height="1" /></noscript>';
    }

    /**
     * Log page edits actions
     */
    public function logedits(Event $event, $param)
    {
        if ($event->data[3]) return; // no revision

        if (file_exists($event->data[0][0])) {
            if ($event->data[0][1] == '') {
                $type = 'D';
            } else {
                $type = 'E';
            }
        } else {
            $type = 'C';
        }
        /** @var helper_plugin_statistics $hlp */
        $hlp = plugin_load('helper', 'statistics');
        $hlp->Logger()->logEdit(cleanID($event->data[1] . ':' . $event->data[2]), $type);
    }

    /**
     * Log internal search
     */
    public function logsearch(Event $event, $param)
    {
        /** @var helper_plugin_statistics $hlp */
        $hlp = plugin_load('helper', 'statistics');
        $hlp->Logger()->logSearch('', $event->data['query'], $event->data['highlight'], 'dokuwiki');
    }

    /**
     * Log login/logouts
     */
    public function loglogins(Event $event, $param)
    {
        global $INPUT;
        
        $type = '';
        $act  = $this->_act_clean($event->data);
        if ($act == 'logout') {
            $type = 'o';
        } elseif ($INPUT->server->str('REMOTE_USER') && $act == 'login') {
            if ($INPUT->str('r')) {
                $type = 'p';
            } else {
                $type = 'l';
            }
        } elseif ($INPUT->str('u') && !$INPUT->str('http_credentials') && !$INPUT->server->str('REMOTE_USER')) {
            $type = 'f';
        }
        if (!$type) return;

        /** @var helper_plugin_statistics $hlp */
        $hlp = plugin_load('helper', 'statistics');
        $hlp->Logger()->logLogin($type);
    }

    /**
     * Log user creations
     */
    public function logregistration(Event $event, $param)
    {
        if ($event->data['type'] == 'create') {
            /** @var helper_plugin_statistics $hlp */
            $hlp = plugin_load('helper', 'statistics');
            $hlp->Logger()->logLogin('C', $event->data['params'][0]);
        }
    }

    /**
     * Log media access
     */
    public function logmedia(Event $event, $param)
    {
        if ($event->data['status'] < 200) return;
        if ($event->data['status'] >= 400) return;
        if (preg_match('/^\w+:\/\//', $event->data['media'])) return;

        // no size for redirect/not modified
        if ($event->data['status'] >= 300) {
            $size = 0;
        } else {
            $size = @filesize($event->data['file']);
        }

        /** @var helper_plugin_statistics $hlp */
        $hlp = plugin_load('helper', 'statistics');
        $hlp->Logger()->logMedia(
            $event->data['media'],
            $event->data['mime'],
            !$event->data['download'],
            $size
        );
    }

    /**
     * Log the daily page and media counts for the history
     */
    public function loghistory(Event $event, $param)
    {
        echo 'Plugin Statistics: started' . DOKU_LF;

        /** @var helper_plugin_statistics $hlp */
        $hlp = plugin_load('helper', 'statistics');

        // check if a history was gathered already today
        $sql = "SELECT `info` FROM " . $hlp->prefix . "history WHERE `dt` = DATE(NOW())";
        $result = $hlp->runSQL($sql);
        if (is_null($result)) {
            global $MSG;
            print_r($MSG);
        }

        $page_ran  = false;
        $media_ran = false;
        foreach ($result as $row) {
            if ($row['info'] == 'page_count')  $page_ran  = true;
            if ($row['info'] == 'media_count') $media_ran = true;
        }

        if ($page_ran && $media_ran) {
            echo 'Plugin Statistics: nothing to do - finished' . DOKU_LF;
            return;
        }

        $event->stopPropagation();
        $event->preventDefault();

        if ($page_ran) {
            echo 'Plugin Statistics: logging media' . DOKU_LF;
            $hlp->Logger()->logHistoryMedia();
        } else {
            echo 'Plugin Statistics: logging pages' . DOKU_LF;
            $hlp->Logger()->logHistoryPages();
        }
        echo 'Plugin Statistics: finished' . DOKU_LF;
    }

    /**
     * Pre-Sanitize the action command
     *
     * Similar to act_clean in action.php but simplified and without
     * error messages
     */
    public function _act_clean($act)
    {
        // check if the action was given as array key
        if (is_array($act)) {
            [$act] = array_keys($act);
        }

        //remove all bad chars
        $act = strtolower($act);
        $act = preg_replace('/[^a-z_]+/', '', $act);

        return $act;
    }
}
