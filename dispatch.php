<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Statistics plugin - data logger
 *
 * This logger is called via JavaScript
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
use dokuwiki\plugin\statistics\IgnoreException;
use dokuwiki\ErrorHandler;

if (!defined('DOKU_INC')) define('DOKU_INC', realpath(__DIR__ . '/../../../') . '/');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
require_once(DOKU_INC . 'inc/init.php');
session_write_close();

global $INPUT;

/** @var helper_plugin_statistics $plugin */
$plugin = plugin_load('helper', 'statistics');
$plugin->sendGIF(); // browser be done

$logger = $plugin->getLogger();
$logger->begin(); // triggers autologging

switch ($INPUT->str('do')) {
    case 'v':
        $logger->logPageView();
        break;
    case 'o':
        $logger->logOutgoing();
}

$logger->end();
