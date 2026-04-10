<footer class="lozad bg-size pt-md-5 " data-background-image="assets/img/footer.webp">
	<div class="container-lg">
		<div class="row py-4 justify-content-between">
			<div class="col-md-3">
				<a href="<?php echo $base_url?>" class="logo d-block" aria-label="Home page">
					<img class="lozad logo" alt="Orbit Book Publishers" data-src="assets/img/logo.png">
				</a>
				<p class="mt-4">Orbit Book Publishers is your trusted partner in book writing, book editing, book publishing, and book marketing. We help storytellers become published authors with expert support every step of the way!</p>
					
			</div>
			<div class="col-md-6 col-lg-5 col-sm-6">
				<h2 class="pb-xl-5 py-4">Services</h2>
				<ul class="list-unstyled mb-0 row row-cols-2 row-gap-3">
					<li class="col">
						<a href="book-publishing-services" class="">Book Publishing</a>
					</li>
					<li class="col">
						<a href="book-video-services" class="">Book Video</a>
					</li>
					<li class="col">
						<a href="book-marketing-services" class="">Book Marketing</a>
					</li>
					<li class="col">
						<a href="book-illustration-services" class="">Illustration & Graphics</a>
					</li>
					<li class="col">
						<a href="book-writing-services" class="">Book
							Writing</a>
					</li>
					<li class="col"><a href="book-cover-services" class="">Book Cover</a>
					</li>
					<li class="col">
						<a href="book-editing-services" class="">Book Editing</a>
					</li>
					<li class="col">
						<a href="author-website-services" class="">Author Website</a>
					</li>
					<li class="col">
						<a href="audio-book-services" class="">Audio Book</a>
					</li>
					
				</ul>
			</div>
			<div class="col-md-3 col-sm-4">
				<h2 class="pb-xl-5 py-4">Contact Info</h2>
				<ul class="list-unstyled mb-0">
					<li class="mb-3">
				        <a href="tel:<?php echo $no ?>" aria-label="Call Us now to discuss you project"
							class="d-flex align-items-center mb-3"><span class="icon-phone me-2"></span>
							<?php echo $no ?>
						</a>
					</li>
					<li class="mb-3">
					    <a href="mailto:sales@<?= $domainname ?>" aria-label="Email us to discuss you project"
							class="d-flex align-items-center mb-3"><span class="icon-mail me-2"></span>
							sales@<?= $domainname ?>
						</a>
					</li>
					<!--<li class="mb-3">-->
					<!--    <span class="d-flex align-items-center mb-3"><span class="icon-location me-2"></span>-->
					<!--	    <?= $add ?>-->
					<!--	</span>-->
					<!--</li>-->
					<li class="mb-3">
					    <ul class="list-unstyled d-flex justify-content-start">
								<li class="me-3 ms-0">
									<a href="https://www.facebook.com/orbitbookpublishers1/" aria-label="Like and follow <?php echo $bname?> facbook page"
										target="_blank"><span class="icon-fb"></span></a>
								</li>
								<li class="ms-0">
									<a href="https://www.instagram.com/orbitbookpublishers/" aria-label="Follow Book <?php echo $bname?> on Instagram"
										target="_blank"><span class="icon-insta"></span></a>
								</li>
							</ul>
					</li>
					<!--<li class="mb-3"><a href="about-us" class="">About Us</a></li>-->
					
				</ul> 
				
							
			</div>
		</div>
		<div class="row py-3 border-top">
		    <div class="col-md-6 col-lg-7 col-xl-8">
		        <p class="f-16">
			                        Copyright © <?php echo date('Y')?> <span class="clr-1 text-uppercase fw-600"><?php echo $bname?></span> All Rights Reserved
			                    </p>
		    </div>
		    <div class="col-md-6 col-lg-5 col-xl-4">
		        <ul class="list-unstyled d-flex justify-content-between">
		            <li class=""><a href="terms-conditions" class="">Terms & Conditions</a></li>
					<li class=""><a href="privacy-policy" class="">Privacy & Policy</a></li>
		        </ul>
		    </div>
		</div>
	</div>

</footer>
<div class="modal fade" id="quote" tabindex="-1" aria-labelledby="quoteLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
		<div class="modal-content lozad clr-l">
			<!-- <div class="modal-header  ">
							
						</div> --><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
			<div class="modal-body ">
				<div class="row">
					<div class="col-md-6 d-lg-block d-none">
						<img class="lozad" data-src="assets/img/popcharacter.webp" alt="<?php echo $bname ?>">
					</div>
					<div class="col-md-6 align-self-center">
						<h4 class="f-24 clr-1 fw-700">Start Your Writing Journey</h4>
						<form action="<?php echo $post_url; ?>" method="post">
							<div class="form-group">
								<input type="hidden" name="domain" value="<?php echo $domainname ?>">
								<input type="hidden" name="ip" value="<?php echo $geoplugin->ip ?>">
								<input type="hidden" name="city" value="<?php echo $geoplugin->city ?>">
								<input type="hidden" name="region" value="<?php echo $geoplugin->region ?>">
								<input type="hidden" name="country" value="<?php echo $geoplugin->countryName ?>">
								<input type="hidden" name="url" value='<?php
								if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
									$url = "https://";
								else
									$url = "http://";
								// Append the host(domain name, ip) to the URL.   
								$url .= $_SERVER['HTTP_HOST'];

								// Append the requested resource location to the URL   
								$url .= $_SERVER['REQUEST_URI'];

								echo $url;
								?>   '>
								<input type="hidden" name="domain" value="<?php echo $domainname ?>">
								<input type="hidden" name="subject" value="Popup Form (<?php echo $url ?>)">
								<input type="hidden" name="source" value="<?php echo $lead_source ?>" />
							</div>
							<div class="form-group mb-3">
								<input class="form-control" type="text" name="name" placeholder="Your Name" required=""
									value="<?php echo !empty($_POST['name']) ? $_POST['name'] : '' ?>">
								<?php echo !empty($error['name']) ? $error['name'] : ''; ?>
							</div>
							<div class="form-group mb-3">
								<input class="form-control" type="email" name="email" placeholder="Your Email"
									value="<?php echo !empty($_POST['email']) ? $_POST['email'] : '' ?>"
									required="required">
								<?php echo !empty($error['email']) ? $error['email'] : ''; ?>
							</div>
							<div class="form-group mb-3">
								<input class="form-control" type="text" name="phone" minlength="10" maxlength="12"
									placeholder="Your Phone"
									value="<?php echo !empty($_POST['phone']) ? $_POST['phone'] : '' ?>"
									required="required">
								<?php echo !empty($error['phone']) ? $error['phone'] : ''; ?>
							</div>
							<div class="form-group mb-3">
								<textarea class="form-control" autocomplete="nope" name="message"
									placeholder="Enter Brief"><?php echo !empty($_POST['message']) ? $_POST['message'] : '' ?></textarea>
								<?php echo !empty($error['message']) ? $error['message'] : ''; ?>
							</div>
							<div class="form-group">
								<button class="btn w-100" cite="Submit" data-hover="Submit" type="submit" name="cta1"
									value="Submit Now">Submit</button>
							</div>
						</form>

					</div>
				</div>
			</div>

		</div>
	</div>
</div>
<script type="text/javascript" src="assets/js/plugin.js"></script>
<script type="text/javascript" src="assets/js/lozad.min.js"></script>
<script type="text/javascript" src="assets/js/custom.js?<?php echo $date ?>"></script>


<script>
	const observer = lozad('.lozad', {
		rootMargin: '10px 0px',
		threshold: 0.1,
		enableAutoReload: true
	});
	observer.observe();
</script>