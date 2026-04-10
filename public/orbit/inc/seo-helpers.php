<?php
declare(strict_types=1);

/**
 * Public site base for Orbit (scheme + host + path up to and including /orbit/).
 */
function orbit_seo_public_base(): string
{
	$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
	$requestPath = str_replace('\\', '/', $requestPath);
	$orbitPos = stripos($requestPath, '/orbit/');
	if ($orbitPos !== false) {
		$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

		return $scheme . '://' . $host . substr($requestPath, 0, $orbitPos + strlen('/orbit/'));
	}

	$dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
	$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

	return rtrim($scheme . '://' . $host . $dir, '/') . '/';
}

/**
 * Absolute URL for a path under Orbit (e.g. "book-writing-services" or "texas/houston").
 */
function orbit_seo_absolute_url(string $path): string
{
	$path = trim(str_replace('\\', '/', $path), '/');
	$base = rtrim(orbit_seo_public_base(), '/');

	return $path === '' ? $base . '/' : $base . '/' . $path;
}

/**
 * Current page canonical URL (no query string).
 */
function orbit_seo_current_canonical_url(): string
{
	$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
	$path = str_replace('\\', '/', $path);
	$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? '';
	$url = $scheme . '://' . $host . $path;
	$url = preg_replace('#/index\.php/?$#i', '/', $url);

	return $url;
}

/**
 * Flatten API tree with optional ISO8601 updated_at per node for sitemap lastmod.
 *
 * @param  array<int, array<string, mixed>>  $nodes
 * @return array<int, array{path:string,lastmod:?string}>
 */
function orbit_seo_flatten_service_entries(array $nodes, string $prefix = ''): array
{
	$out = [];
	foreach ($nodes as $node) {
		if (! is_array($node)) {
			continue;
		}
		$slug = isset($node['slug']) ? (string) $node['slug'] : '';
		if ($slug === '') {
			continue;
		}
		$full = $prefix === '' ? $slug : $prefix . '/' . $slug;
		$lm = $node['updated_at'] ?? null;
		$out[] = [
			'path' => $full,
			'lastmod' => is_string($lm) && $lm !== '' ? $lm : null,
		];
		$children = $node['children'] ?? [];
		if (is_array($children) && $children !== []) {
			$out = array_merge($out, orbit_seo_flatten_service_entries($children, $full));
		}
	}

	return $out;
}

/**
 * Title + description for static PHP pages (when not using CMS service payload).
 *
 * @return array{title:string,description:string}|null
 */
function orbit_seo_static_page_meta(string $slug): ?array
{
	$brand = 'Orbit Book Publishers';
	$map = [
		'audio-book-services' => [
			'title' => "Audiobook Production & Narration | {$brand}",
			'description' => "Professional audiobook services at {$brand}: narration, production, and distribution support for authors.",
		],
		'author-website-services' => [
			'title' => "Author Websites & Branding | {$brand}",
			'description' => "Author website design and branding services to showcase your books and connect with readers.",
		],
		'book-cover-services' => [
			'title' => "Book Cover Design Services | {$brand}",
			'description' => "Custom book cover design that sells your story—fiction, non-fiction, and series branding.",
		],
		'book-editing-services' => [
			'title' => "Book Editing & Proofreading | {$brand}",
			'description' => "Developmental editing, copy editing, and proofreading to polish your manuscript for publication.",
		],
		'book-illustration-services' => [
			'title' => "Book Illustration Services | {$brand}",
			'description' => "Custom illustrations for children’s books, chapter art, and cover visuals from {$brand}.",
		],
		'book-marketing-services' => [
			'title' => "Book Marketing & Promotion | {$brand}",
			'description' => "Book marketing, Amazon optimization, and author promotion strategies to grow your readership.",
		],
		'book-publishing-services' => [
			'title' => "Book Publishing Services | {$brand}",
			'description' => "End-to-end book publishing support: formatting, printing, distribution, and guidance for authors.",
		],
		'book-video-services' => [
			'title' => "Book Trailers & Video Marketing | {$brand}",
			'description' => "Book trailer and promotional video services to showcase your book on social and retail pages.",
		],
		'book-writing-services' => [
			'title' => "Ghostwriting & Book Writing Services | {$brand}",
			'description' => "Professional ghostwriters and book writing services to turn your ideas into a finished manuscript.",
		],
	];

	return $map[$slug] ?? null;
}

/**
 * Paths to list in sitemap (no .php), excluding thank-you and system pages.
 *
 * @return array<int, string>
 */
function orbit_seo_sitemap_static_paths(): array
{
	return [
		'',
		'audio-book-services',
		'author-website-services',
		'book-cover-services',
		'book-editing-services',
		'book-illustration-services',
		'book-marketing-services',
		'book-publishing-services',
		'book-video-services',
		'book-writing-services',
		'location',
		'privacy-policy',
		'terms-conditions',
	];
}

function orbit_seo_xml_escape(string $s): string
{
	return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
