<!DOCTYPE html>
<html lang="en">

<head>
	<base href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>">
	<meta charset="utf-8">
	<meta name="p:domain_verify" content="bc955305a2d7b16ce43b37fc0decf7ec"/>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	require_once __DIR__ . '/seo-helpers.php';
	$pageSlug = isset($page) ? preg_replace('/\.php$/', '', (string) $page) : '';
	$canonical_url = orbit_seo_current_canonical_url();
	$script = '';
	$og_type = 'website';

	if (! empty($orbit_service_payload['service'])) {
		$class = 'service-dynamic';
		$svc = $orbit_service_payload['service'];
		$title = ! empty($svc['meta_title']) ? $svc['meta_title'] : (($svc['title'] ?? 'Service') . ' | Orbit Book Publishers');
		$discription = $svc['meta_description'] ?? '';
		$robots = 'INDEX, FOLLOW';
	} elseif ($pageSlug === 'location') {
		$class = 'location-page';
		$title = 'Locations | Orbit Book Publishers';
		$discription = 'Explore where Orbit Book Publishers serves authors across the United States. Find book publishing and marketing support in your state.';
		$robots = 'INDEX, FOLLOW';
	} elseif ($pageSlug === 'home' || $pageSlug === '') {
		$class = 'home';
		$title = 'Orbit book publishers | Providing Book Services to Authors';
		$discription = 'Orbit Book Publishers provides book writing, book publishing, book marketing services to all authors, whether you’re a new author or an experienced one. Call Us ';
		$robots = 'INDEX, FOLLOW';
	} elseif ($pageSlug === 'privacy-policy') {
		$class = 'privacy-policy';
		$title = 'Privacy Policy | Orbit Book Publishers ';
		$discription = 'Read the privacy policy of Orbit Book Publishers and read about how we protect your data and the precautions we take to process it.';
		$robots = 'INDEX, FOLLOW';
	} elseif ($pageSlug === 'terms-conditions') {
		$class = 'terms-conditions';
		$title = 'Terms & Conditions of Orbit Book Publishers';
		$discription = 'Read Orbit Book Publishers’ terms and conditions and get transparent guidelines about our terms.';
		$robots = 'INDEX, FOLLOW';
	} elseif ($pageSlug === '404') {
		$class = '404';
		$title = '404 | Oops.. Page Not Found | Orbit Book Publishers';
		$discription = '404 - This page does not exist on Orbit Book Publishers and we’re sorry for the inconvenience.';
		$robots = 'NOINDEX, NOFOLLOW';
	} elseif ($pageSlug === 'thankyou') {
		$class = 'thankyou';
		$title = 'Thank You for Contacting to Orbit Book Publishers';
		$discription = 'Thank you for reaching out to Orbit Book Publishers. One of our representatives will contact you soon!';
		$robots = 'NOINDEX, NOFOLLOW';
	} elseif (($staticMeta = orbit_seo_static_page_meta($pageSlug)) !== null) {
		$class = $pageSlug;
		$title = $staticMeta['title'];
		$discription = $staticMeta['description'];
		$robots = 'INDEX, FOLLOW';
	} else {
		$class = preg_match('/^[a-z0-9_-]+$/i', $pageSlug) ? $pageSlug : 'page';
		$title = 'Orbit book publishers | Providing Book Services to Authors';
		$discription = 'Orbit Book Publishers provides book writing, book publishing, book marketing services to all authors, whether you’re a new author or an experienced one. Call Us ';
		$robots = 'INDEX, FOLLOW';
	}

	$site_base = rtrim((string) $url, '/');
	$og_image = (string) $logo;
	if ($og_image !== '' && ! preg_match('#^https?://#i', $og_image)) {
		$og_image = rtrim(orbit_seo_absolute_url(''), '/') . '/' . ltrim($og_image, '/');
	}
	if ($og_image === '') {
		$og_image = rtrim(orbit_seo_absolute_url(''), '/') . '/assets/img/banner.webp';
	}

	$graph = [];
	$org_id = $site_base . '#organization';
	$graph[] = [
		'@type' => 'Organization',
		'@id' => $org_id,
		'name' => $bname,
		'url' => $site_base,
		'logo' => ['@type' => 'ImageObject', 'url' => $logo],
		'telephone' => $no,
		'address' => [
			'@type' => 'PostalAddress',
			'streetAddress' => $add,
		],
	];
	$graph[] = [
		'@type' => 'WebSite',
		'@id' => $site_base . '#website',
		'url' => $site_base,
		'name' => $bname,
		'publisher' => ['@id' => $org_id],
	];

	if (! empty($orbit_service_payload['service'])) {
		$svc = $orbit_service_payload['service'];
		$graph[] = [
			'@type' => 'Service',
			'@id' => $canonical_url . '#service',
			'name' => $svc['title'] ?? 'Service',
			'description' => $discription !== '' ? $discription : ($svc['meta_description'] ?? ''),
			'url' => $canonical_url,
			'provider' => ['@id' => $org_id],
		];
		$trail = $svc['breadcrumb'] ?? [];
		if (is_array($trail) && $trail !== []) {
			$items = [];
			$acc = [];
			$pos = 1;
			foreach ($trail as $cr) {
				if (! is_array($cr)) {
					continue;
				}
				$slug = (string) ($cr['slug'] ?? '');
				if ($slug === '') {
					continue;
				}
				$acc[] = $slug;
				$items[] = [
					'@type' => 'ListItem',
					'position' => $pos++,
					'name' => (string) ($cr['title'] ?? ''),
					'item' => orbit_seo_absolute_url(implode('/', $acc)),
				];
			}
			if ($items !== []) {
				$graph[] = [
					'@type' => 'BreadcrumbList',
					'@id' => $canonical_url . '#breadcrumb',
					'itemListElement' => $items,
				];
			}
		}
	}

	$schema_json = json_encode(
		['@context' => 'https://schema.org', '@graph' => $graph],
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
	);
	if ($schema_json === false) {
		$schema_json = '{"@context":"https://schema.org"}';
	}
	?>
	
	<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
	<meta name="description" content="<?php echo htmlspecialchars($discription, ENT_QUOTES, 'UTF-8'); ?>">
	<meta name="robots" content="<?php echo htmlspecialchars($robots, ENT_QUOTES, 'UTF-8'); ?>" />
	<link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
	<meta property="og:type" content="<?php echo htmlspecialchars($og_type, ENT_QUOTES, 'UTF-8'); ?>">
	<meta property="og:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
	<meta property="og:description" content="<?php echo htmlspecialchars($discription, ENT_QUOTES, 'UTF-8'); ?>">
	<meta property="og:url" content="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
	<meta property="og:site_name" content="<?php echo htmlspecialchars($bname, ENT_QUOTES, 'UTF-8'); ?>">
	<meta property="og:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
	<meta name="twitter:description" content="<?php echo htmlspecialchars($discription, ENT_QUOTES, 'UTF-8'); ?>">
	<meta name="twitter:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo $script ?>
	<script type="application/ld+json"><?php echo $schema_json; ?></script>

<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="https://orbitbookpublishers.com/" />
<link rel="manifest" href="/site.webmanifest" />

    <link rel="preload" as="font" type="font/woff2" href="assets/css/fonts/HTx3L3I-JCGChYJ8VI-L6OO_au7B6xHT2g.woff2" crossorigin="anonymous" media="all" />
	<link rel="preload" as="font" type="font/woff2" href="assets/css/fonts/HTxwL3I-JCGChYJ8VI-L6OO_au7B46r2z3bWuQ.woff2" crossorigin="anonymous" media="all" />
	<link rel="preload" as="font" type="font/woff2" href="assets/css/fonts/iJWEBXyIfDnIV7nEnX661A.woff2" crossorigin="anonymous" media="all" />

	<link rel="preload" as="font" type="font/woff" href="assets/css/fonts/slick.woff" crossorigin="anonymous" media="all" />
	<link rel="preload" as="font" type="font/ttf" href="assets/css/fonts/slick.ttf" crossorigin="anonymous" media="all" />
	<link rel="preload" as="font" type="font/eot" href="assets/css/fonts/slick.eot" crossorigin="anonymous" media="all" />

	<link rel="preload" as="font" type="font/eot" href="assets/css/fonts/icomoon.eot" crossorigin="anonymous" media="all" />
	<link rel="preload" as="font" type="font/ttf" href="assets/css/fonts/icomoon.ttf" crossorigin="anonymous" media="all" />
	<link rel="preload" as="font" type="font/woff" href="assets/css/fonts/icomoon.woff" crossorigin="anonymous" media="all" />
		
	<link rel="preconnect" href="https://cdn.jsdelivr.net">
	<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
	<link rel="stylesheet" rel="preload" as="style" type="text/css" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css" />
	<link rel="stylesheet" rel="preload" as="style" type="text/css" href="assets/css/vendor.css?<?php echo $date ?>" crossorigin="anonymous" media="all" />
	<link rel="stylesheet" rel="preload" as="style" type="text/css" href="assets/css/style.css?<?php echo $date ?>" crossorigin="anonymous" media="all" />


	<link rel="preload" fetchpriority="high" as="image" href="assets/img/logo.webp" type="image/webp">
	<link rel="preload" fetchpriority="high" as="image" href="assets/img/banner.webp" type="image/webp">
	<link rel="preload" fetchpriority="high" as="image" href="assets/img/feature/feature-1.webp" type="image/webp">
	<link rel="preload" fetchpriority="high" as="image" href="assets/img/feature/feature-2.webp" type="image/webp">
	<link rel="preload" fetchpriority="high" as="image" href="assets/img/feature/feature-3.webp" type="image/webp">
    
    
</head>

<body class="<?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>">
    <script type="text/javascript" src="assets/js/jquery-3.7.1.min.js"></script>
   
	
