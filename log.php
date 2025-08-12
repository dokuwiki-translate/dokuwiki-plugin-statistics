<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Statistics plugin - data logger
 *
 * This logger is called via JavaScript or the no-script fallback
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

use dokuwiki\ErrorHandler;

if (!defined('DOKU_INC')) define('DOKU_INC', realpath(__DIR__ . '/../../../') . '/');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
require_once(DOKU_INC . 'inc/init.php');
session_write_close();

global $INPUT;

try {
    /** @var helper_plugin_statistics $plugin */
    $plugin = plugin_load('helper', 'statistics');
    $plugin->sendGIF(); // browser be done

    $logger = $plugin->getLogger();
    $logger->begin();
    $logger->logLastseen(); // refresh session

    switch ($INPUT->str('do')) {
        case 'v':
            $logger->logAccess();
            $logger->logSession(1);
            break;
        case 'o':
            $logger->logOutgoing();
            $logger->logSession();
            break;
        default:
            $logger->logSession();
    }

    $logger->end();
} catch (\Exception $e) {
    if (!$e instanceof dokuwiki\plugin\statistics\IgnoreException) {
        ErrorHandler::logException($e);
    }
}
