<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/orbit-api-service.php';

$orbitServiceData = orbitGetParentServices(ORBIT_BRAND_SLUG);
$listUrl = $orbitServiceData['list_url'];
$orbitParentServices = $orbitServiceData['items'];
?>

<section class="main banner lozad bg position-relative z-3" data-background-image="assets/img/book-editing.webp">
	<div class="overlay position-relative py-5 z-3">
		<img class="lozad sub-ban-left" alt="Book services by <?= $bname; ?>"
			data-src="assets/img/editing-before.webp">
		<div class="container-lg py-md-5 py-4 ">
			<div class="row justify-content-center text-center py-5">
				<div class="col-xxl-8 col-lg-9 col-md-11 col-12 pt-lg-5 ">
					<p class="fw-600 clr-2 mb-2 text-uppercase small">Nationwide coverage</p>
					<h1 class="f-55 c fw-700 mt-2 clr-1">We Are Where <span class="bg-2 clr-l">You Are</span> — Serving Authors Across the USA</h1>
					<p class="fw-600">Orbit Book Publishers brings trusted book publishing, marketing, and support to authors in cities and towns nationwide. Explore our service areas below and connect with us from your hometown.</p>
				</div>
				<div class="col-lg-11 col-xl-10 col-12 pt-4">
					<?php include 'inc/inner-form.php'; ?>
				</div>
			</div>
		</div>
	</div>
</section>
<?php include 'inc/platform.php'; ?>

<section class="orbit-location-section">
	<div class="container-lg">
		<div class="orbit-location-head">
			<h2 class="f-40 clr-1 fw-700 mb-3">We are in your hometown now!</h2>
			<p class="f-18 fw-500 mb-0">
				We are extending our services to serve you better in your own hometown. Below is a list of geographic locations where we currently offer our services.
				If you don&rsquo;t see your state listed, please contact us &mdash; we may still be able to assist you.
			</p>
		</div>
		<?php if ($orbitParentServices === []) { ?>
			<p class="text-center text-muted mt-4 mb-0">Locations could not be loaded from the API, or no parent services exist yet. Ensure <code><?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?></code> returns data.</p>
		<?php } else { ?>
		<div class="orbit-state-grid" role="list">
			<?php foreach ($orbitParentServices as $node) {
				$title = $node['title'] ?? '';
				$slug = $node['slug'] ?? '';
				if ($slug === '') {
					continue;
				}
				$servicePageHref = rtrim($base_url, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', trim((string) $slug, '/'))));
				?>
			<a class="orbit-state-pill btn" role="listitem" href="<?= htmlspecialchars($servicePageHref, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(orbitSafeUpper((string) $title), ENT_QUOTES, 'UTF-8'); ?></a>
			<?php } ?>
		</div>
		<?php } ?>
	</div>
</section>
