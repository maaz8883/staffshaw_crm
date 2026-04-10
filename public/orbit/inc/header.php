<?php
$directoryURI = $_SERVER['REQUEST_URI'];
$paths = parse_url($directoryURI, PHP_URL_PATH) ?: '/';
$components = explode('/', $paths);
if (!empty($components[1]) && $components[1] === 'index.php' && isset($components[2])) {
	$first_part = $components[2];
} else {
	$first_part = $components[1] ?? '';
}

?>
<header>

	<div class="container-xxl py-3 ">
		<div class="row align-items-center justify-content-between">

			<div class="col-3 col-md-4 d-lg-none">
				<span class="nav-open icona">
					<span class="icon">☰</span>
				</span>
			</div>

			<div class="col-lg-2 col-md-4 col-6">
				<a href="<?php echo $base_url?>" class="logo d-block" aria-label="Home page">
					<img class=" logo" alt="<?php echo $bname?>" src="assets/img/logo.webp">
				</a>
			</div>

			<div class="col-xl-7 col-lg-8 col-md-8 nav-bar">
				<nav>
					<ul class="list-unstyled orbit-add mb-0 d-lg-flex justify-content-lg-evenly justify-content-between position-relative fw-600">
						<li class="d-lg-none">
							<div class="row justify-content-between align-items-center">

								<div class="col-3">
									<span class="nav-open icon">
										X
									</span>
								</div>

								<div class="col-6">
									<a href="<?php echo $base_url?>" class="logo d-block"
										aria-label="Home page">
										<img class="lozad logo" alt="<?php echo $bname?>"
											data-src="assets/img/logo.webp">
									</a>
								</div>

								<div class="col-3">
									<a href="tel:<?php echo $no ?>" aria-label="Call Us now to discuss you project"
										class="d-flex justify-content-end icona"><span class="icon"><span
												class="icon-phone"></span></span>
									</a>
								</div>
							</div>
						</li>
						<li>
							<a href="book-publishing-services" class="<?php echo $first_part == 'book-publishing' ? 'active' : '' ?>">Book Publishing</a>
						</li>
						<li>
							<a href="book-editing-services" class="<?php echo $first_part == 'book-editing' ? 'active' : '' ?>">Book Editing</a>
						</li>
						<li>
							<a href="book-marketing-services" class="<?php echo $first_part == 'book-marketing' ? 'active' : '' ?>">Book Marketing</a>
						</li>
						<li>
							<a href="book-writing-services" class="<?php echo $first_part == 'Book-writing' ? 'active' : '' ?>">Book Writing</a>
						</li>
						<li class="drop-down"> <span class="" style="color: #fff;">More Services ▼</span>
							<div class="drop-down-cont">
								
								<?php $headerlist=['book-editing','Author-Website','audio-book','book-video','book-cover','illustration'];
								$first_class = in_array($first_part, $headerlist) ? 'active clr-1' : ''; ?>
								<ul class="row list-unstyled p-lg-3 ps-3">
									
									<li class="mb-lg-3 col-6"><a href="author-website-services" class="<?php echo $first_part == 'author-website' ? 'active' : '' ?>"><img
												class="lozad nav-icon d-none d-lg-inline"
												data-src="assets/img/nav-icons/design-service/icon-2.webp"
												alt="Author Website By <?php echo $bname?>"
												
												>Author Website</a></li>
									<li class="mb-lg-3 col-6"><a href="audio-book-services" class="<?php echo $first_part == 'audio-book' ? 'active' : '' ?>"><img
												class="lozad nav-icon d-none d-lg-inline"
												data-src="assets/img/nav-icons/design-service/audio-book.webp"
												alt="Audio Book By <?php echo $bname?>"
												
												>Audio Book</a></li>
									<li class="mb-lg-3 col-6"><a href="book-video-services" class="<?php echo $first_part == 'book-video' ? 'active' : '' ?>"><img
												class="lozad nav-icon d-none d-lg-inline"
												data-src="assets/img/nav-icons/design-service/icon-5.webp"
												alt="Book Video By <?php echo $bname?>"
												
												>Book Video</a></li>
									<li class="mb-lg-3 col-6"><a href="book-cover-services" class="<?php echo $first_part == 'book-cover' ? 'active' : '' ?>"><img
												class="lozad nav-icon d-none d-lg-inline"
												data-src="assets/img/nav-icons/design-service/icon-10.webp"
												alt="Book Cover Design By <?php echo $bname?>"
												
												>Book Cover Design</a></li>
									<li class="mb-lg-3 col-6"><a href="book-illustration-services" class="<?php echo $first_part == 'illustration' ? 'active' : '' ?>"><img
												class="lozad nav-icon d-none d-lg-inline"
												data-src="assets/img/nav-icons/design-service/icon-18.webp"
												alt="Illustration By <?php echo $bname?>"
												>Illustration & Graphics</a></li>
								</ul>
							</div>
						</li>
						<li class="d-lg-none heading f-18 pt-3 border-top">Contact Info</li>
						<li class="d-lg-none">
							<a href="tel:<?php echo $no ?>" aria-label="Call Us now to discuss you project"
								class="d-flex align-items-center mb-3"><span class="icon-phone me-2"></span>
								<?php echo $no ?></a>
							<a href="mailto:sales@<?= $domainname ?>" aria-label="Email us to discuss you project"
								class="d-flex align-items-center mb-3"><span class="icon-mail me-2"></span>
								sales@<?= $domainname ?></a>
							<span
								class="d-flex align-items-center mb-3"><span class="icon-location me-2"></span>
								<?= $add ?></span>
							<ul class="list-unstyled d-flex justify-content-start">
								<li class="me-3 ms-0">
									<a href="https://www.facebook.com/macleanspublishers/" aria-label="Like and follow Macleans Publisher's facbook page"
										target="_blank"><span class="icon-fb"></span></a>
								</li>
								<li class="ms-0">
									<a href="https://www.instagram.com/macleanspublishersofficial/" aria-label="Follow Book <?php echo $bname?>'s on Instagram"
										target="_blank"><span class="icon-insta"></span></a>
								</li>
							</ul>
						</li>
					</ul>
				</nav>
			</div>

			<div class="col-xl-3 col-lg-2 d-none d-lg-block text-center ps-lg-0">
				<button class="px-xl-5 px-lg-4 text-nowrap" data-bs-toggle="modal" data-bs-target="#quote">Get Started</button>
			</div>

			<div class="col-3 col-md-4 d-lg-none text-end">
				<a href="tel:<?php echo $no ?>" aria-label="Call Us now to discuss you project"
					class="d-flex justify-content-end icona"><span class="icon"><span class="icon-phone"></span></span>
				</a>
			</div>

		</div>
	</div>

</header>