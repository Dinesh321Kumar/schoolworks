<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['site_license_key'] = '';
// ExpressionEngine Config Items
// Find more configs and overrides at
// https://docs.expressionengine.com/latest/general/system-configuration-overrides.html

$config['app_version'] = '7.4.10';
$config['encryption_key'] = '19b6eaf35550f3b20e612ba403afae64ff107b1c';
$config['session_crypt_key'] = '111dd070481210d288a9c98864ecb062e8568613';
$config['database'] = array(
	'expressionengine' => array(
		'hostname' => 'localhost',
		'database' => 'schoolwork',
		'username' => 'root',
		'password' => '',
		'dbprefix' => 'exp_',
		'char_set' => 'utf8mb4',
		'dbcollat' => 'utf8mb4_unicode_ci',
		'port'     => ''
	),
);
$config['show_ee_news'] = 'y';
$config['share_analytics'] = 'y';

// EOF