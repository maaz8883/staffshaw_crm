<?php
// $cacheDir = __DIR__ . '/cache/';
// if (!is_dir($cacheDir)) {
//     mkdir($cacheDir, 0755, true);
// }

// $cachefile = $cacheDir . md5($_SERVER['REQUEST_URI']) . '.php';
// $cachetime = 3600; // 1 hour

// // If cached version exists and is still fresh → serve it
// if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile))) {
//     readfile($cachefile);
//     exit;
// }

// Start buffering with minifier callback
ob_start("minifier");

function minifier($code) {
    $search = array(
        '/\>[^\S ]+/s',        // remove spaces after tags
        '/[^\S ]+\</s',        // remove spaces before tags
        '/(\s)+/s',            // collapse multiple spaces
        '/<!--(.|\s)*?-->/'    // remove HTML comments
    );
    $replace = array('>', '<', '\\1');
    return preg_replace($search, $replace, $code);
}



$reporting = false;
if ($reporting) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
}
// Cache folder (make sure it's writable)
// $cacheDir = __DIR__ . '/cache/';
// if (!is_dir($cacheDir)) {
//     mkdir($cacheDir, 0755, true);
// }

// $cachefile = $cacheDir . md5($_SERVER['REQUEST_URI']) . '.php';
// $cachetime = 3600; // 1 hour

// // If a cached version exists and is still fresh → serve it
// if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile))) {
//     readfile($cachefile);
//     exit;
// }

// // Start output buffering
// ob_start();

$test_email = null;

// ob_start("minifier");
// function minifier($code)
// {
// 	$search = array(
// 		'/\>[^\S ]+/s',
// 		'/[^\S ]+\</s',
// 		'/(\s)+/s',
// 		'/<!--(.|\s)*?-->/'
// 	);
// 	$replace = array('>', '<', '\\1');
// 	$code = preg_replace($search, $replace, $code);
// 	return $code;
// }

$http_referer = $_SERVER['HTTP_REFERER'] ?? '';
if ($http_referer !== '' && false !== stripos($http_referer, "https://www.bing.com/")) {
    $lead_source = "Bing PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://bing.com/")) {
    $lead_source = "Bing PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://www.bing.com/aclk")) {
    $lead_source = "Bing PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://bing.com/aclk")) {
    $lead_source = "Bing PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://www.googleadservices.com/")) {
    $lead_source = "Google PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://googleadservices.com/")) {
    $lead_source = "Google PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://www.googleadservices.com/pagead/")) {
    $lead_source = "Google PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://googleadservices.com/pagead/")) {
    $lead_source = "Google PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://www.googleadservices.com/pagead/aclk")) {
    $lead_source = "Google PPC";
} elseif ($http_referer !== '' && false !== stripos($http_referer, "https://googleadservices.com/pagead/aclk")) {
    $lead_source = "Google PPC";
} else {
    $lead_source = "Organic";
}
$current_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$post_url = htmlspecialchars((isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . str_replace('index.php/', '', $_SERVER["PHP_SELF"]));

// REQUEST_URI-based routing: .htaccess sends /orbit/texas/houston → index.php/texas/houston, which breaks
// dirname(SCRIPT_NAME) and makes $base_url not match — nested pages then get wrong $path/$page and 404.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$requestPath = str_replace('\\', '/', $requestPath);
$orbitPos = stripos($requestPath, '/orbit/');
if ($orbitPos !== false) {
	$base_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
	$base_url .= substr($requestPath, 0, $orbitPos + strlen('/orbit/'));

	$relative = substr($requestPath, $orbitPos + strlen('/orbit/'));
	if (stripos($relative, 'index.php/') === 0) {
		$relative = substr($relative, strlen('index.php/'));
	} elseif (strcasecmp($relative, 'index.php') === 0) {
		$relative = '';
	}
	$relative = trim($relative, '/');
	if (preg_match('#\.php$#i', $relative)) {
		$relative = preg_replace('#\.php$#i', '', $relative);
	}
	if ($relative === '' || strcasecmp($relative, 'index') === 0) {
		$page = 'home';
		$path = '';
	} else {
		$parts = array_values(array_filter(explode('/', $relative), static fn ($s) => $s !== ''));
		$page = array_pop($parts);
		$path = count($parts) ? implode('/', $parts) . '/' : '';
	}
	if (strpos($page, '.php') !== false) {
		$page = str_replace('.php', '', $page);
	}
	$page = (empty($page) || $page == 'index') ? 'home' : $page;
	$page .= '.php';
} else {
	$base_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
	$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
	$base_url .= preg_replace('@/+$@', '', $script_dir) . '/';
	$path = str_replace($base_url, '', $current_url);
	$path_prams = explode('?', $path);
	$path = $path_prams[0];
	$pages = explode('/', $path);
	if (!empty($pages) && count($pages) > 0) {
		$page = array_pop($pages);
		$path = implode('/', $pages);
		$path = !empty($path) ? $path . '/' : '';
	} else {
		$page = $path;
		$path = '';
	}
	if (strpos($page, '.php') !== false) {
		$page = str_replace('.php', '', $page);
	}
	$page = (empty($page) || $page == 'index') ? 'home' : $page;
	$page .= '.php';
}
$path_prams = explode('?', str_replace($base_url, '', $current_url));
$prams = !empty($path_prams[1]) ? $path_prams[1] : '';
$exampted_pages = array('thankyou.php', '404.php', 'enroll-now.php', 'logo.php', 'website.php', 'brief/seo.php', 'smm.php', 'create_link.php', 'link_details.php', 'paynow.php', 'charge.php', 'fail.php', 'confirm_payment.php', 'publishing-experts/index.php');
$exampt_allfiles = array('create_payment.php', 'confirm_payment.php');
$no = "+1 (754) 225-2490";
$add = '300 Peachtree St NE Ste CS2, 30308, Atlanta, United States';
$url = "https://orbitbookpublishers.com/";
$bname = "Orbit Book Publishers";
$date = date('d-m-y_h:i:s');
$logo = 'https://orbitbookpublishers.com/assets/img/logo-black.png';

// $title =  'Welcome To US Logo Designs';
// $keywords =  '';
// $description =  '';
$domainname = "orbitbookpublishers.com";

$error_prefex = "<p class='alert-danger'>";
$error_sufex = "</p>";

$smtp['host'] = 'mail.orbitbookpublishers.com';
$smtp['username'] = 'info@orbitbookpublishers.com';
//$smtp['password'] = 'qrkx gmkd nvxg htpv';
$smtp['password'] = '!Computer@123';
$smtp['port'] = '465';

$from_email = "noreply@orbitbookpublishers.com";
$from_name = $bname;

$source = $_POST['source'] ?? 'organic';
// $lead_source = "Organic";
// $source = "organic";
$cta_email = "info@orbitbookpublishers.com";
$cta_subject = "New $source Lead on $bname ";
$cta_template = __DIR__ . "/email_templates/cta.php";
$cta2_template = __DIR__ . "/email_templates/cta2.php";
$cta3_template = __DIR__ . "/email_templates/cta3.php";
$cta4_template = __DIR__ . "/email_templates/cta4.php";
$cta5_template = __DIR__ . "/email_templates/cta5.php";


$brief_email = "brief@orbitbookpublishers.com/";

$webbrief_subject = "New Web Brief Recived from $bname";
$webbrief_template = __DIR__ . "/email_templates/webbrief.php";

$logobrief_subject = "New Web Brief Recived from $bname";
$logobrief_template = __DIR__ . "/email_templates/logobrief.php";

$seobrief_subject = "New SEO Brief Recived from $bname";
$seobrief_template = __DIR__ . "/email_templates/seobrief.php";

$smmbrief_subject = "New SMM Brief Recived from $bname";
$smmbrief_template = __DIR__ . "/email_templates/smmbrief.php";

$payment_email = "billing@orbitbookpublishers.com/";

$invoicelink_subject = "New Payment Invoice Link Recived from $bname";
$invoicelink_template = __DIR__ . "/email_templates/invoice_link.php";

$payment_recived_subject = "New Payment Has Recived from $bname";
$payment_recived_template = __DIR__ . "/email_templates/payment_recived.php";

$activePage = basename($_SERVER['PHP_SELF'], ".php");

require_once('geoplugin.class.php');

$geoplugin = new geoPlugin();
$geoplugin->locate();

$orbit_service_payload = null;
