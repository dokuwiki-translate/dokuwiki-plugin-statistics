<?php

/**
 * Statistics plugin - data logger
 *
 * This logger is called via JavaScript or the no-script fallback
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

if (!defined('DOKU_INC')) define('DOKU_INC', realpath(__DIR__ . '/../../../') . '/');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
require_once(DOKU_INC . 'inc/init.php');
session_write_close();


/** @var helper_plugin_statistics $plugin */
$plugin = plugin_load('helper', 'statistics');
$plugin->sendGIF(); // browser be done

$logger = $plugin->Logger();
$logger->begin();
$logger->logLastseen(); // refresh session

switch ($_REQUEST['do']) {
    case 'v':
        $logger->logAccess();
        $logger->logSession(1);
        break;

    /** @noinspection PhpMissingBreakStatementInspection */
    case 'o':
        $logger->logOutgoing();

    //falltrough
    default:
        $logger->logSession();
}

$logger->end();
