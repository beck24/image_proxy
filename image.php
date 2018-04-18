<?php

namespace image_proxy;

error_reporting(0); // we don't want notices etc to break the image data passthrough

$settings = dirname(dirname(dirname(__FILE__))) . '/elgg-config/settings.php';

$fail = function() {
	header('Content-Type: image/png');
	readfile('graphics/proxyfail.png');
	exit;
};

if (!file_exists($settings)) {
	$fail();
}

require_once($settings);

global $CONFIG;

$url = $_GET['url'];
$token = $_GET['token'];
$secret = '';

if ($CONFIG->image_proxy_secret) {
	$secret = $CONFIG->image_proxy_secret;
} else {
	$secret = file_get_contents($CONFIG->dataroot . 'image_proxy_secret.txt');
}

if (!$secret) {
	$fail();
}

if ($token !== sha1($secret . $url)) {
	$fail();
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
	$fail();
}

// we must find a content type of image
$found_img = false;
$forwarded_headers = [];

foreach (explode("\r\n", $headers) as $header) {
	if (strpos($header, 'HTTP/') === 0) {
		// In case of redirects, we only consider headers after the last response line,
		// so we reset state here.
		$found_img = false;
		$forwarded_headers = [];
		continue;
	}

	if (false === strpos($header, ':')) {
		continue;
	}

	list ($name, $value) = explode(':', $header, 2);
	$lower_name = strtolower($name);
	$value = trim($value);

	if ($lower_name === 'content-type' && strpos($value, 'image/') === 0) {
		$found_img = true;
		$forwarded_headers[] = $header;
		continue;
	}

	if (in_array($lower_name, ['age', 'cache-control', 'date', 'expires', 'last-modified'])) {
		$forwarded_headers[] = $header;
	}
}

if (!$found_img) {
	$fail();
}

foreach ($forwarded_headers as $header) {
	header($header);
}

// TODO: eliminate need for PHP to make 2nd request here. Probably would want to use
// streams: fopen(), stream_get_meta_data()['wrapper_data'], and fpassthru().
readfile($url);
exit;
