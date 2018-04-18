<?php

namespace image_proxy;

elgg_register_event_handler('init', 'system', function() {
	elgg_register_plugin_hook_handler('view', 'all', __NAMESPACE__ . '\\view_hook');
});

/**
 * replace any http images with https urls
 * 
 * @param string $h "view"
 * @param string $t view name
 * @param string $html view output
 * @param array $p hook params
 * @return string
 */
function view_hook($h, $t, $html, $p) {
	if (false === strpos($html, '<img')) {
		// exit fast for most views
		return $html;
	}
	
	$http_url = str_replace('https://', 'http://', elgg_get_site_url());

	if (preg_match_all( '/<img[^>]+src\s*=\s*["\']?([^"\' ]+)[^>]*>/', $html, $extracted_image)) {
		foreach ($extracted_image[0] as $key => $img) {
			$url = $extracted_image[1][$key];
			
			if (strpos($url, elgg_get_site_url()) !== false) {
				continue; // already one of our links
			}

			// check if this is our url being requested over http, and rewrite to https
			if (strpos($url, $http_url) === 0) {
				$new_url = str_replace('http://', 'https://', $url);
				$replacement_image = str_replace($url, $new_url, $img);
				$html = str_replace($img, $replacement_image, $html);
				continue;
			}

			if (strpos($url, 'http:') === 0) {
				// replace with proxy URL
				// URL may contain "&amp;" or some other HTML encoded character
				$real_url = html_entity_decode($url, ENT_HTML5, 'UTF-8');
				$new_url = elgg_normalize_url('mod/image_proxy/image.php?') . http_build_query([
					'url' => $real_url,
					'token' => get_token($real_url),
				]);
				$new_url = htmlspecialchars($new_url, ENT_QUOTES, 'UTF-8');
				$replacement_img = str_replace($url, $new_url, $img);
				$html = str_replace($img, $replacement_img, $html);
			}
		}
	}
	
	return $html;
}


function get_token($url) {
	$secret = get_secret();
	
	return sha1($secret . $url);
}


function get_secret() {
	static $secret;
	if ($secret) {
		return $secret;
	}

	if (elgg_get_config('image_proxy_secret')) {
		$secret = elgg_get_config('image_proxy_secret');
	}
	else {
		// generate our own
		$dataroot = elgg_get_config('dataroot');
		if (file_exists($dataroot . 'image_proxy_secret.txt')) {
			$secret = file_get_contents($dataroot . 'image_proxy_secret.txt');
		}
		else {
			$secret = _elgg_services()->crypto->getRandomString(128, \ElggCrypto::CHARS_PASSWORD);
			file_put_contents($dataroot . 'image_proxy_secret.txt', $secret);
		}
	}

	return $secret;
}