<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/seo-helpers.php';

$baseUrl = rtrim(orbit_seo_public_base(), '/');
$sitemap = $baseUrl . '/sitemap.xml';
$pathPrefix = parse_url($baseUrl, PHP_URL_PATH) ?: '';
$pathPrefix = rtrim(str_replace('\\', '/', (string) $pathPrefix), '/');

header('Content-Type: text/plain; charset=UTF-8');

echo "User-agent: *\n";
echo "Allow: /\n";

$p = static function (string $suffix) use ($pathPrefix): string {
	$suffix = ltrim($suffix, '/');

	return ($pathPrefix !== '' ? $pathPrefix . '/' : '/') . $suffix;
};

echo 'Disallow: ' . $p('thankyou') . "\n";
echo 'Disallow: ' . $p('404') . "\n";
echo 'Disallow: ' . $p('create_payment.php') . "\n";
echo 'Disallow: ' . $p('confirm_payment.php') . "\n";
echo 'Disallow: ' . $p('publishing-experts432/') . "\n";
echo "\n";
echo 'Sitemap: ' . $sitemap . "\n";
