<?php
declare(strict_types=1);

/**
 * "As featured in" logo strip: theme defaults (assets/img/feature/*.webp) or CMS URLs.
 *
 * Expects $c (service content) in scope from orbit_render_dynamic_service.
 */
$fi = $c['featured_in'] ?? [];
if (! is_array($fi)) {
	$fi = [];
}
$useDefault = true;
if (array_key_exists('use_default_logos', $fi)) {
	$v = $fi['use_default_logos'];
	$useDefault = $v === true || $v === 1 || $v === '1';
}
if ($useDefault) {
	include __DIR__ . '/platform.php';

	return;
}
$logos = $fi['logos'] ?? [];
if (! is_array($logos)) {
	$logos = [];
}
$logos = array_values(array_filter($logos, static function ($u) {
	return is_string($u) && trim($u) !== '';
}));
if ($logos === []) {
	return;
}
?>
<section class="py-5 platforms">
	<div class="container-xl">
		<div class="slider">
			<?php foreach ($logos as $img) {
				$u = orbitMediaUrl($img);
				if ($u === '') {
					continue;
				}
				$pathPart = parse_url($u, PHP_URL_PATH) ?: $u;
				$title = pathinfo(basename($pathPart), PATHINFO_FILENAME);
				?>
			<div class="item">
				<div class="platform">
					<img data-lazy="<?= orbit_e($u); ?>" alt="<?= orbit_e($title); ?> — <?= orbit_e($bname ?? ''); ?>"/>
				</div>
			</div>
				<?php
			} ?>
		</div>
	</div>
</section>
