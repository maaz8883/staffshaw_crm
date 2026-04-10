<?php
$orbit_form_ip = isset($geoplugin->ip) ? (string) $geoplugin->ip : '';
$orbit_form_city = isset($geoplugin->city) ? (string) $geoplugin->city : '';
$orbit_form_region = isset($geoplugin->region) ? (string) $geoplugin->region : '';
$orbit_form_country = isset($geoplugin->countryName) ? (string) $geoplugin->countryName : '';
$orbit_form_current_url = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$orbit_form_current_url .= $_SERVER['HTTP_HOST'] ?? '';
$orbit_form_current_url .= $_SERVER['REQUEST_URI'] ?? '';
$orbit_form_subject = 'Popup Form (' . $orbit_form_current_url . ')';
?>
<form action="<?php echo htmlspecialchars((string) ($post_url ?? ''), ENT_QUOTES, 'UTF-8'); ?>" method="post" class="row">
	<div class="form-group">
		<input type="hidden" name="domain" value="<?php echo htmlspecialchars((string) ($domainname ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="ip" value="<?php echo htmlspecialchars($orbit_form_ip, ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="city" value="<?php echo htmlspecialchars($orbit_form_city, ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="region" value="<?php echo htmlspecialchars($orbit_form_region, ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="country" value="<?php echo htmlspecialchars($orbit_form_country, ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="url" value="<?php echo htmlspecialchars($orbit_form_current_url, ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="domain" value="<?php echo htmlspecialchars((string) ($domainname ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="subject" value="<?php echo htmlspecialchars($orbit_form_subject, ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="source" value="<?php echo htmlspecialchars((string) ($lead_source ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
	</div>
	<div class="form-group mb-3 col-lg col-md-4 col-sm-6"><input class="form-control" type="text" name="name"
			placeholder="Your Name" required="" value=""></div>
	<div class="form-group mb-3 col-lg col-md-4 col-sm-6"><input class="form-control" type="email"
			name="email" placeholder="Your Email" value="" required="required"></div>
	<div class="form-group mb-3 col-lg col-md-4 col-sm-6">
		<input class="form-control" type="text" name="phone" minlength="10" maxlength="14">
	</div>
	<div class="form-group mb-3 col-lg col-md-8	col-sm-6"><input class="form-control" autocomplete="nope"
			name="message" placeholder="Enter Brief" rows="4"></div>
	<div class="form-group col-lg col-md-4"><button class="btn w-100" cite="Submit"
			data-hover="Submit" type="submit" name="cta1" value="Submit Now">Submit</button></div>
</form>
