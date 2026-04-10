<?php
// function webpReplace($matches)
// {
//     [$full, $prefix, $path, $ext, $suffix] = $matches;
	
//     return $prefix . (file_exists($path . '.webp') ? $path . '.webp' : $path . '.' . $ext) . $suffix;
// }
// ob_start();
// below code paste after html end tag 

//*** */
// $html = ob_get_clean();
// $html = preg_replace_callback('/(?:(<img[^>]*(?:src|data-src)=["\'])([^"\']*)\.(png|jpg|jpeg)(["\'][^>]*>)|(url\(["\']?)([^"\']*)\.(png|jpg|jpeg)(["\']?\)))/', 'webpReplace', $html);
// echo $html;
// ob_end_flush();
//*** */

function getImagePath($folder, $filename)
{
	return file_exists($folder . $filename . '.webp') ? $folder . $filename . '.webp' :
		(file_exists($folder . $filename . '.png') ? $folder . $filename . '.png' :
			(file_exists($folder . $filename . '.jpg') ? $folder . $filename . '.jpg' : null));
};


function getClientDetails()
{
	global $domainname, $geoplugin, $lead_source;
	$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	$output = '<div class="form-group">
        <input type="hidden" name="domain" value="' . $domainname . '">
        <input type="hidden" name="ip" value="' . $geoplugin->ip . '">
        <input type="hidden" name="city" value="' . $geoplugin->city . '">
        <input type="hidden" name="region" value="' . $geoplugin->region . '">
        <input type="hidden" name="country" value="' . $geoplugin->countryName . '">
        <input type="hidden" name="url" value="' . $url . '">
        <input type="hidden" name="subject" value="Popup Form (' . $url . ')">
        <input type="hidden" name="source" value="' . $lead_source . '">
    </div>';
	return $output;
};
function getAnchorButton($href, $className, $text)
{
	$output = "<a href='{$href}' class='{$className}'>{$text}</a>";
	return $output;
};

function getModalButton($className, $text)
{
	$output = "<button type='button' class='{$className}'  data-bs-toggle='modal' data-bs-target='#quote'>{$text}</button>";
	return $output;
};
function getChatButton($className, $text)
{
	$output = "<a type='button'href='' class='{$className}' role='button'>{$text}</a>";
	return $output;
};
function getChatButton1($className, $text)
{
	return '<button type="button" class="' . $className . '" data-bs-toggle="modal" data-bs-target="#quote">' . $text . '</button>';
};
function logo($logo_name)
{
	$imagePath = getImagePath("assets/img/", $logo_name);
	global $base_url, $bname;
	$output = '<a href="' . $base_url . '" class="logo d-block" aria-label="Home page">
        <img class="lozad logo" alt="' . $bname . '" data-src="' . $imagePath . '"></a>';
	return $output;
};
function phonButton()
{
	global $no;
	$output = '<a href="tel:' . $no . '" aria-label="Call Us now to discuss you project" class="d-flex justify-content-end align-items-center icona"><span class="icon"><span class="icon-phone"></span></span> </a>';
	return $output;
};


$socialiconSet = true;
$sname = str_replace(['-', ' '], '', strtolower($bname));
$socialIcon = [
	'facebook' => [
		'anchor' => "https://www.facebook.com/people/Beverly-Publishers/61576854488922/",
		'aria-label' => "Like and follow $bname's facbook page",
		'icon' => "icon-fb"
	],
	'instagram' => [
		'anchor' => "https://www.instagram.com/$sname/",
		'aria-label' => "Follow $bname's on Instagram",
		'icon' => "icon-insta"
	],
	'pinterest' => [
		'anchor' => "https://www.pinterest.com/$sname/",
		'aria-label' => "Follow $bname's on Pinterest",
		'icon' => "icon-pinterest2"
	],
	// 	'linkedin' => [
	// 		'anchor' => "https://www.pinterest.com/$sname/",
	// 		'aria-label' => "Follow $bname's on Instagram",
	// 		'icon' => "icon-linkedin2"
	// 	],
];


function getSocialIcon()
{
	global $socialIcon, $socialiconSet;
	if ($socialiconSet) {
		$output = '<ul class="list-unstyled mb-0 d-flex column-gap-2 justify-content-start social-icon">';
		foreach ($socialIcon as $socialIconName => $socialIconValue) {
			$output .= '<li class="mx-0"><a class="icona" href="' . $socialIconValue['anchor'] . '" aria-label="' . $socialIconValue['aria-label'] . '" target="_blank"><span class="icon"><span class="' . $socialIconValue['icon'] . '"></span></span></a></li>';
		}
		$output .= '</ul>';
		return $output;
	}
	return '';
};

$contactDetails = [
	'phone' => [
		'name' => 'phone',
		'anchor' => 'tel:' . $no,
		'aria-label' => "Call Us now to discuss you project",
		'value' => $no
	],
	'mail' =>
		[
			'name' => 'email',
			'anchor' => 'mailto:sales@' . $domainname,
			'aria-label' => "Email us to discuss you project",
			'value' => 'sales@' . $domainname
		],
	'location' =>
		[
			'name' => 'address',
			'aria-label' => "Find us at this address",
			'value' => $add
		],


];
function getContactDetails()
{
	global $contactDetails;
	$output = '';
	foreach ($contactDetails as $contactName => $contactValue) {
		if ($contactName == 'location') {
			$output .= '<span aria-label="' . $contactValue['aria-label'] . '" class="d-flex align-items-center mb-3"><span class="icon-' . $contactName . ' me-2"></span>' . $contactValue['value'] . '</span>';
		} else {
			$output .= '<a href="' . $contactValue['anchor'] . '" aria-label="' . $contactValue['aria-label'] . '" class="d-flex align-items-center mb-3"><span class="icon-' . $contactName . ' me-2"></span>' . $contactValue['value'] . '</a>';
		}
	}
	return $output;

};

function getContactDetailsFooter(){
	global $contactDetails;
	$output='<ul class="list-unstyled mb-0"> ';
	foreach ($contactDetails as $contactName => $contactValue){
		if($contactName == 'location'){
			$output.= '<li class="mb-2"><span aria-label="'.$contactValue['aria-label'].'">'. ucwords($contactValue['name']) . ': <span>' . $contactValue['value'].'</span></span></li>';
		} else {
			$output.= '<li class="mb-2"><a href="'.$contactValue['anchor'].'" aria-label="'.$contactValue['aria-label'].'>'. ucwords($contactValue['name']) . ': <span class="" class="text-decoration-underline">' . $contactValue['value'].'</span></a></li>';
		}
	}
	$output .= '</ul>';
	return $output;
};

