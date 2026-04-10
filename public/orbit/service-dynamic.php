<?php
declare(strict_types=1);

if (empty($orbit_service_payload['service'])) {
	header('Location: ' . $base_url . '404');
	exit;
}

require_once __DIR__ . '/inc/orbit-service-dynamic-render.php';
orbit_render_dynamic_service($orbit_service_payload);
