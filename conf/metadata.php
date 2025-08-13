<?php

/**
 * Options for the statistics plugin
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */

$meta['loggroups']   = array('array');
$meta['anonips']     = array('onoff');
$meta['nolocation'] = array('onoff');
$meta['nousers']     = array('onoff');
$meta['retention']   = array('numeric', '_min' => 0, '_pattern' => '/\d+/', '_caution' => 'warning');
