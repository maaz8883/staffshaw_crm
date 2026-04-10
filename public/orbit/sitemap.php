<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/orbit-api-config.php';
require_once __DIR__ . '/inc/orbit-api-service.php';
require_once __DIR__ . '/inc/seo-helpers.php';

if (! defined('ORBIT_BRAND_SLUG')) {
	define('ORBIT_BRAND_SLUG', 'orbit');
}

$now = gmdate('c');

$urls = [];

foreach (orbit_seo_sitemap_static_paths() as $p) {
	$loc = orbit_seo_absolute_url($p);
	$urls[] = [
		'loc' => $loc,
		'lastmod' => $now,
		'changefreq' => $p === '' ? 'weekly' : 'monthly',
		'priority' => $p === '' ? '1.0' : '0.8',
	];
}

$tree = orbitGetParentServices(ORBIT_BRAND_SLUG);
foreach (orbit_seo_flatten_service_entries($tree['items'] ?? []) as $entry) {
	$path = $entry['path'] ?? '';
	if ($path === '') {
		continue;
	}
	$lm = $entry['lastmod'] ?? null;
	$urls[] = [
		'loc' => orbit_seo_absolute_url($path),
		'lastmod' => $lm !== null && $lm !== '' ? $lm : $now,
		'changefreq' => 'weekly',
		'priority' => '0.7',
	];
}

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $u) {
	echo "  <url>\n";
	echo '    <loc>' . orbit_seo_xml_escape($u['loc']) . "</loc>\n";
	echo '    <lastmod>' . orbit_seo_xml_escape($u['lastmod']) . "</lastmod>\n";
	echo '    <changefreq>' . orbit_seo_xml_escape($u['changefreq']) . "</changefreq>\n";
	echo '    <priority>' . orbit_seo_xml_escape($u['priority']) . "</priority>\n";
	echo "  </url>\n";
}

echo "</urlset>\n";
