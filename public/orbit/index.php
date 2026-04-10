<?php

require 'inc/global.php';
require 'inc/form.php';

$orbit_service_payload = null;

$orbitIndexFile = static function (string $pathPrefix, string $pageFile): string {
	$pathPrefix = str_replace('\\', '/', $pathPrefix);
	$pageFile = str_replace('\\', '/', $pageFile);
	$pageFile = ltrim($pageFile, '/');
	if ($pathPrefix === '') {
		return __DIR__ . '/' . $pageFile;
	}

	return __DIR__ . '/' . rtrim($pathPrefix, '/') . '/' . $pageFile;
};

if (! in_array($page, $exampted_pages)) {

	if (! file_exists($orbitIndexFile($path, $page))) {
		require_once __DIR__ . '/inc/orbit-api-service.php';
		$dynPath = orbit_request_service_path_from_uri();
		if ($dynPath !== null) {
			$orbit_service_payload = orbitFetchServiceShow(ORBIT_BRAND_SLUG, $dynPath);
			if (is_array($orbit_service_payload) && ! empty($orbit_service_payload['service'])) {
				$page = 'service-dynamic.php';
				$path = '';
			}
		}
	}

	if (! file_exists($orbitIndexFile($path, $page))) {
		header('Location: ' . $base_url . '404');
		die();
	}
	require __DIR__ . '/inc/head.php';
	require __DIR__ . '/inc/header.php';
	require $orbitIndexFile($path, $page);
	require __DIR__ . '/inc/footer.php';
	require __DIR__ . '/inc/chat.php';

} elseif (in_array($page, $exampt_allfiles)) {
	if (! file_exists($orbitIndexFile($path, $page))) {
		header('Location: ' . $base_url . '404');
		die();
	}
	require $orbitIndexFile($path, $page);

} else {
	if (! file_exists($orbitIndexFile($path, $page))) {
		header('Location: ' . $base_url . '404');
	}
	require __DIR__ . '/inc/head.php';
	require $orbitIndexFile($path, $page);
	require __DIR__ . '/inc/chat.php';
}
