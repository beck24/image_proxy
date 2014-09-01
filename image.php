<?php

namespace image_proxy;

error_reporting(0); // we don't want notices etc to break the image data passthrough

// Get DB settings
require_once(dirname(dirname(dirname(__FILE__))) . '/engine/settings.php');

global $CONFIG;

$url = urldecode($_GET['url']);
$token = $_GET['token'];

if ($CONFIG->image_proxy_secret) {
	$site_secret = $CONFIG->image_proxy_secret;
} else {
	$mysql_dblink = @mysql_connect($CONFIG->dbhost, $CONFIG->dbuser, $CONFIG->dbpass, true);
	if ($mysql_dblink) {
		if (@mysql_select_db($CONFIG->dbname, $mysql_dblink)) {
			$result = mysql_query("select name, value from {$CONFIG->dbprefix}datalists where name = '__site_secret__'", $mysql_dblink);
			if ($result) {
				$row = mysql_fetch_object($result);
				while ($row) {
					if ($row->name == '__site_secret__') {
						$site_secret = $row->value;
					}
					$row = mysql_fetch_object($result);
				}
			}

			@mysql_close($mysql_dblink);
		}
	}
}

if (!$site_secret) {
	error_log('Cannot find site secret');
	header('HTTP/1.0 404 Not Found');
	exit;
}

if ($token !== md5($site_secret . $url)) {
	error_log('Invalid proxy token');
	header('HTTP/1.0 404 Not Found');
	exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // in seconds
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_NOBODY, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$headers = curl_exec($ch);

if ($headers === false) {
	// we couldn't get the headers from the remote url
	error_log('Could not retrieve remote headers');
	header('HTTP/1.0 404 Not Found');
	exit;
}

foreach (explode("\r\n", $headers) as $header) {
	header($header);
}

readfile($url);
exit;
