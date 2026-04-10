<?php require '../inc/global.php';?>
<?php require '../inc/form.php';?>

<!DOCTYPE html>
<html>

<head>

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8">
<title>Book Publishing Services | E-Book, Paperback and Hardback Distribution</title>
<meta name="description" content="AE-Book, Paperback and Hardback Distribution">
<link rel="icon" type="image/png" href="../assets/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg" />
	<link rel="shortcut icon" href="../assets/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="../assets/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="Book Publisher Avenue" />
	<link rel="manifest" href="../assets/site.webmanifest" />
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Bootstrap CSS -->

<link r-href="assets/css/animate.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link href="assets/slick/slick-theme.css" rel="stylesheet" type="text/css">
<link href="assets/slick/slick.css" rel="stylesheet" type="text/css">
<link href="assets/css/slicknav.css" rel="stylesheet" type="text/css">
<link r-href="assets/css/fancybox.css" rel="stylesheet" type="text/css">
<link href="assets/css/bootstrap.css" rel="stylesheet">
<link href="assets/css/custom.css" rel="stylesheet">
<link href="assets/css/responsive.css" rel="stylesheet">
<link rel="preconnect" href="https://cdn.jsdelivr.net"><link rel="dns-prefetch" href="https://cdn.jsdelivr.net"><link rel="stylesheet" rel="preload" as="style" type="text/css"	href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css" />


</head>
<body>
	<header>
	<div class="menuSec">
		<div class="container">
			<div class="row align-items-center">
				<div class="col-md-5 col-lg-3 col-sm-6 col-12">
					<div class="header-logo">
						<a href="<?php echo $base_url?>"><img src="assets/images/logo.webp" alt="img"></a>
					</div>
				</div>
				<div class="col-md-7 col-lg-9 col-sm-6 col-12 text-right">
					<div class="header_btns">
						<ul>
							<li>
								<a href="tel:<?php echo $no?>">
									<img src="assets/images/headphones.png" alt="Call Icon">
									<h5>Have Any Questions? <span><?php echo $no?></span></h5>
								</a>
							</li>
							<li>
								<a href="javascript:;" onclick="LiveChatWidget.call('maximize')" class="btn-1">Live Chat <i class="fas fa-chevron-circle-right"></i></a>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</header>

<div class="banner-wrap">
	<div class="banner-back wow fadeInUp" data-wow-duration="2s">
		<img src="assets/images/banner.webp" class="w-100" alt="">
	</div>
	<div class="after-banner">
		<img src="assets/images/banner_after.png" alt="">
	</div>
	<div class="banner-center wow fadeInDown" data-wow-duration="2s">
		<img src="assets/images/banner-cntr.webp" alt="">
	</div>
	<div class="banner-cont">
		<div class="container">
			<div class="row justify-content-between">
				<div class="col-lg-4 wow fadeInLeft" data-wow-duration="2s">
					<h1>Bring Your Book Idea to Life With <span> </span> <?php echo $bname?></h1>
					<h4>E-Book, Paperback, Hardback & More</h4>
					<p>At <?php echo $bname?>, we recognize that writing a compelling book or manuscript is just the beginning of your journey as an author. Successfully publishing and marketing your book is equally crucial to ensuring its success.</p>
					<div class="btn-flex">
						<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Let’s Start <i class="fas fa-chevron-circle-right"></i></a>
						<a href="javascript:;" onclick="LiveChatWidget.call('maximize')">Chat now</a>
					</div>
				</div>
				<div class="col-lg-4">
					<form class="form-get-quote"  action="<?php echo $post_url; ?>" method="post">
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
								
								<input type="hidden" name="subject" value="Popup Form (<?php echo $url ?>)">
								<input type="hidden" name="source" value="<?php echo $lead_source ?>" />
						<div class="banner-form ">
							<div class="for-head">
								<img src="assets/images/form-png.png" alt="">
								<span><span>50%</span> OFF </span>
								ON BOOK PUBLICATION <br> SERVICES
							</div>
							<div class="form-body">
								<div class="input-field">
									<input type="text" name="name" placeholder="Your Name" required=""
									value="<?php echo !empty($_POST['name']) ? $_POST['name'] : '' ?>">
								<?php echo !empty($error['name']) ? $error['name'] : ''; ?>
									<i class="fa-solid fa-user"></i>
								</div>
								<div class="input-field">
									<input type="text" name="phone" minlength="10" maxlength="12"
									placeholder="Your Phone"
									value="<?php echo !empty($_POST['phone']) ? $_POST['phone'] : '' ?>"
									required="required">
								<?php echo !empty($error['phone']) ? $error['phone'] : ''; ?>
									<i class="fa-solid fa-phone"></i>
								</div>
								<div class="input-field">
									<input type="email" name="email" placeholder="Your Email"
									value="<?php echo !empty($_POST['email']) ? $_POST['email'] : '' ?>"
									required="required">
								<?php echo !empty($error['email']) ? $error['email'] : ''; ?>
									<i class="fa-solid fa-envelope"></i>
								</div>
								<div class="input-field">
									<textarea autocomplete="nope" name="message" rows="4"
									placeholder="Enter Brief"><?php echo !empty($_POST['message']) ? $_POST['message'] : '' ?></textarea>
								<?php echo !empty($error['message']) ? $error['message'] : ''; ?>
									<i class="fas fa-paper-plane"></i>
								</div>
								<div class="text-center">
									<button class="btn-1"cite="Submit" data-hover="Submit" type="submit" name="cta1"
									value="Submit Now">SUBMIT <i class="fas fa-chevron-circle-right"></i></button>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
 <!-- work Section Start -->
<section class="work-sec">
	<!--<img src="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%200%200'%3E%3C/svg%3E" data-src="assets/images/wrk-1.webp" alt="" class="lazy wrk-1 lazy">-->
	<div class="container">
		<div class="row">
			<div class="col-lg-6 col-md-12">
				<div class="work-image wow fadeInLeft" data-wow-duration="2s">
					<img src="assets/images/work-1.webp" alt="Handwrytten" data-src="assets/images/work-1.webp" alt="" class="lazy lazy">
				</div>
			</div>
			<div class="col-lg-6 col-md-12">
				<div class="work-text wow fadeInRight" data-wow-duration="2s">
					<h2>How We Work</h2>
					<p><?php echo $bname?> allows their authors to retain the flexibility, control, ownership and
						royalty benefits of self-publishing, while offering them global distribution, outreach, and
						a team of professionals to review and overview
					every step of your distribution process.</p>
					<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">GET A QUOTE <i class="fas fa-chevron-circle-right" aria-hidden="true"></i></a>
				</div>
			</div>
		</div>
	</div>
</section>
    <!-- work Section End -->
 <!-- Distribution Section Start -->
<section class="distribute-sec all-section">
	<div class="container-fluid">
		<div class="row">
			<div class="col-lg-7 col-md-12 centerCol">
				<div class="sec-head wow bounceIn" data-wow-duration="2s">
					<h2>Distributors</h2>
					<p>We work with a wide array of online and physical distributors, working endlessly to ensure your work reaches the right audience. </p>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="brand-slider">
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-1.png" alt="Handwrytten" data-src="assets/images/br-1.png" alt="" class="lazy br-1 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-2.png" alt="Handwrytten" data-src="assets/images/br-2.png" alt="" class="lazy br-2 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-3.png" alt="Handwrytten" data-src="assets/images/br-3.png" alt="" class="lazy br-3 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-4.png" alt="Handwrytten" data-src="assets/images/br-4.png" alt="" class="lazy br-4 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-5.png" alt="Handwrytten" data-src="assets/images/br-5.png" alt="" class="lazy br-5 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-6.png" alt="Handwrytten" data-src="assets/images/br-6.png" alt="" class="lazy br-6 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-7.png" alt="Handwrytten" data-src="assets/images/br-7.png" alt="" class="lazy br-7 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-1.png" alt="Handwrytten" data-src="assets/images/br-1.png" alt="" class="lazy br-1 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-2.png" alt="Handwrytten" data-src="assets/images/br-2.png" alt="" class="lazy br-2 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-3.png" alt="Handwrytten" data-src="assets/images/br-3.png" alt="" class="lazy br-3 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-4.png" alt="Handwrytten" data-src="assets/images/br-4.png" alt="" class="lazy br-4 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-5.png" alt="Handwrytten" data-src="assets/images/br-5.png" alt="" class="lazy br-5 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-6.png" alt="Handwrytten" data-src="assets/images/br-6.png" alt="" class="lazy br-6 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-7.png" alt="Handwrytten" data-src="assets/images/br-7.png" alt="" class="lazy br-7 lazy">
					</div>
				</div>
			</div>
			<div class="brand-slider-2">
			    <div>
					<div class="brand-imag">
						<img src="assets/images/br-1.png" alt="Handwrytten" data-src="assets/images/br-1.png" alt="" class="lazy br-1 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-2.png" alt="Handwrytten" data-src="assets/images/br-2.png" alt="" class="lazy br-2 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-3.png" alt="Handwrytten" data-src="assets/images/br-3.png" alt="" class="lazy br-3 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-4.png" alt="Handwrytten" data-src="assets/images/br-4.png" alt="" class="lazy br-4 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-5.png" alt="Handwrytten" data-src="assets/images/br-5.png" alt="" class="lazy br-5 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-6.png" alt="Handwrytten" data-src="assets/images/br-6.png" alt="" class="lazy br-6 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-7.png" alt="Handwrytten" data-src="assets/images/br-7.png" alt="" class="lazy br-7 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/brr-8.webp" alt="Handwrytten" data-src="assets/images/brr-8.webp" alt="" class="lazy brr-8">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-1.png" alt="Handwrytten" data-src="assets/images/br-1.png" alt="" class="lazy br-1 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-2.png" alt="Handwrytten" data-src="assets/images/br-2.png" alt="" class="lazy br-2 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-3.png" alt="Handwrytten" data-src="assets/images/br-3.png" alt="" class="lazy br-3 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-4.png" alt="Handwrytten" data-src="assets/images/br-4.png" alt="" class="lazy br-4 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-5.png" alt="Handwrytten" data-src="assets/images/br-5.png" alt="" class="lazy br-5 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-6.png" alt="Handwrytten" data-src="assets/images/br-6.png" alt="" class="lazy br-6 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/br-7.png" alt="Handwrytten" data-src="assets/images/br-7.png" alt="" class="lazy br-7 lazy">
					</div>
				</div>
				<div>
					<div class="brand-imag">
						<img src="assets/images/brr-8.webp" alt="Handwrytten" data-src="assets/images/brr-8.webp" alt="" class="lazy brr-8">
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
    <!-- Distribution Section End -->
 <!-- Process Section Start -->
<section class="process-sec all-section">
	<div class="container">
		<div class="row">
			<div class="col-lg-8 col-md-6 centerCol">
				<div class="proces-head">
					<h2>Our Process</h2>
				</div>
			</div>
		</div>
		<div class="row mt-5">
			<div class="col-md-4 col-lg mb-4">
				<div class="proces-box">
					<h3>01</h3>
					<h5>Select a Plan</h5>
					<p>To begin, choose the plan that best suits your needs.</p>
				</div>
			</div>
			<div class="col-md-4 col-lg mb-4">
				<div class="proces-box">
					<h3>02</h3>
					<h5>Submit Manuscript</h5>
					<p>Once you've chosen your plan, we proceed to an editorial review.</p>
				</div>
			</div>
			<div class="col-md-4 col-lg mb-4">
				<div class="proces-box">
					<h3>03</h3>
					<h5>Manuscript Preparation</h5>
					<p>Our editors refine your manuscript, ensuring it's error-free and ready for publishing.</p>
				</div>
			</div>
			<div class="col-md-4 col-lg mb-4">
				<div class="proces-box">
					<h3>04</h3>
					<h5>Distributor Setup</h5>
					<p>Next, we arrange for distributors to publish your book.</p>
				</div>
			</div>
			<div class="col-md-4 col-lg mb-4">
				<div class="proces-box">
					<h3>05</h3>
					<h5>Book Publication</h5>
					<p>Finally, your book is published.</p>
				</div>
			</div>
		</div>
	</div>
</section>
    <!-- Process Section End -->
 <!-- Portfolio Section Start -->
<section class="portfolio-sec">
	<div class="container">
		<div class="row">
			<div class="col-lg-10 centerCol">
				<div class="sec-head wow bounceIn" data-wow-duration="2s">
					<h2>Our ever-expanding <span class="theme-span">Book Publishing</span> Portfolio</h2>
					<p>Our book publishing portfolio exemplifies our proficiency in delivering tailored book publishing and marketing services, including self-publishing, to help authors achieve their publishing goals and reach their target readers. </p>
				</div>
			</div>
		</div>
		<div class="row book-slider sliderz">
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInDown" data-wow-duration="2s">
					<img src="assets/images/book-1.webp" alt="Handwrytten" data-src="assets/images/book-1.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-2.webp" alt="Handwrytten" data-src="assets/images/book-2.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInDown" data-wow-duration="2s">
					<img src="assets/images/book-3.webp" alt="Handwrytten" data-src="assets/images/book-3.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-4.webp" alt="Handwrytten" data-src="assets/images/book-4.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-5.webp" data-src="assets/images/book-5.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-6.webp" data-src="assets/images/book-6.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-7.webp" data-src="assets/images/book-7.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-8.webp" data-src="assets/images/book-8.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-9.webp" data-src="assets/images/book-9.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-12.webp" data-src="assets/images/book-12.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-11.webp" data-src="assets/images/book-11.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-13.webp" data-src="assets/images/book-13.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-14.webp" data-src="assets/images/book-14.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-15.webp" data-src="assets/images/book-15.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-16.webp" data-src="assets/images/book-16.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-17.webp" data-src="assets/images/book-17.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-18.webp" data-src="assets/images/book-18.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-19.webp" data-src="assets/images/book-19.webp" class="lazy">
				</div>
			</div>
			<div class="col-lg-3 col-md-3">
				<div class="book-box wow fadeInUp" data-wow-duration="2s">
					<img src="assets/images/book-20.webp" data-src="assets/images/book-20.webp" class="lazy">
				</div>
			</div>
		</div>
	</div>
</section>
    <!-- Portfolio Section End -->
 <!-- pricing Section Start -->
    <section class="package-sec">
        <div class="container">
            <div class="row">
                <div class="col-lg-7 centerCol">
                    <div class="sec-head wow bounceIn" data-wow-duration="2s">
                        <h2>Packages We Offer</h2>
                        <p>We offer an array of packages, providing exceptional book publishing and marketing services without breaking the bank. </p>
                    </div>
                </div>
            </div>
            <div class="row package-slidr sliderz">
                <div class="col-lg-4 col-md-6">
                    <div class="package-box wow fadeInLeft" data-wow-duration="2s">
                        <div class="package-top">
                            <h6>Starter Plan</h6>
                            <h4 class="text-center">$399 <del>$799</del></h4>
                        </div>
                        <ul class="package-list">
                            
                            <li class=""><i class="fas fa-check-circle"></i> Editing/Formatting according to IPS </li> 
                            <li class=""><i class="fas fa-check-circle"></i> Cover, Back, Spine Design </li> 
                            <li class=""><i class="fas fa-check-circle"></i> Publishing the book in all 3 formats (eBook, paperback and Hardcover) </li> 
                            <li class=""><i class="fas fa-check-circle"></i> Global availability on Amazon, KDP, Barnes &amp; Noble </li> 
                            <li class=""><i class="fas fa-check-circle"></i> Unlimited revisions </li> 
                            <li class=""><i class="fas fa-check-circle"></i> ISBN Barcodes </li>
                            <li class=""><i class="fas fa-check-circle"></i> Copyrights </li>
                            <li class=""><i class="fas fa-check-circle"></i> 100% ownership </li>
                            <li class=""><i class="fas fa-check-circle"></i> 100% satisfaction </li> 
                            <li class=""><i class="fas fa-check-circle"></i> 100% royalties </li>
                        </ul>
                        <div class="package-button">
                            <a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Book Now <i class="fas fa-chevron-circle-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="package-box wow fadeInRight" data-wow-duration="2s">
                        <div class="package-top">
                            <h6>Advance Plan</h6>
                            <h4 class="text-center">$799 <del>$1,599</del></h4>   
                        </div>
                        <ul class="package-list">
                            
                                <li><i class="fas fa-check-circle"></i>
                                Editing/Proofreading of the complete manuscript</li>
                                <li><i class="fas fa-check-circle"></i>Formatting according to IPS</li>
                                <li><i class="fas fa-check-circle"></i>Cover, Back, Spine Design</li>
                                <li><i class="fas fa-check-circle"></i>Images/illustrations</li>
                                <li><i class="fas fa-check-circle"></i>Author Central Page Creation</li><li>Unlimited revisions</li>
                                <li><i class="fas fa-check-circle"></i>Publishing the book in all 3 formats (eBook, paperback and Hardcover)</li>
                                <li><i class="fas fa-check-circle"></i>Global availability on Amazon, KDP, other 10+ Platforms</li>
                                <li><i class="fas fa-check-circle"></i>2 Unique Book cover design according to your instructions</li>
                                <li><i class="fas fa-check-circle"></i>Publication Consultancy</li>
                                <li><i class="fas fa-check-circle"></i>ISBN Barcodes</li>
                                <li><i class="fas fa-check-circle"></i>Copyrights</li>
                                <li><i class="fas fa-check-circle"></i>100% ownership</li>
                                <li><i class="fas fa-check-circle"></i>100% royalties</li>
                                <li><i class="fas fa-check-circle"></i>100% satisfaction guarantee</li>
                        </ul>
                        <div class="package-button">
                            <a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Book Now <i class="fas fa-chevron-circle-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="package-box wow fadeInRight" data-wow-duration="2s">
                        <div class="package-top">
                            <h6>Premium Plan</h6>
                            <h4 class="text-center">$1,499 <del>$3,000</del></h4>   
                        </div>
                        <ul class="package-list">
                            
                                
                                <li><i class="fas fa-check-circle"></i>
                                Editing/Proofreading of the complete manuscript</li>
                                <li><i class="fas fa-check-circle"></i>Interior Design (Black and White / Full Color)</li>
                                <li><i class="fas fa-check-circle"></i>Formatting according to IPS</li>
                                <li><i class="fas fa-check-circle"></i>Typesetting/ Layout Adjustments</li>
                                <li><i class="fas fa-check-circle"></i>Images/illustrations</li>
                                <li><i class="fas fa-check-circle"></i>Author Central Page Creation</li>
                                <li><i class="fas fa-check-circle"></i>Unlimited revisions</li>
                                <li><i class="fas fa-check-circle"></i>Publishing the book in all 3 formats (eBook, paperback and Hardcover)</li>
                                <li><i class="fas fa-check-circle"></i>Global availability on Amazon, Kdp, B&amp;N Press, Apple &amp; Google Book Store and 40,000+ other platforms</li>
                                <li><i class="fas fa-check-circle"></i>3 Unique Book cover design according to your instruction</li>
                                <li><i class="fas fa-check-circle"></i>Publication Consultancy</li>
                                <li><i class="fas fa-check-circle"></i>ISBN Barcodes</li>
                                <li><i class="fas fa-check-circle"></i>Author &amp; Book Website Setup</li>
                                <li><i class="fas fa-check-circle"></i>Copyrights</li>
                                <li><i class="fas fa-check-circle"></i>100% ownership</li>
                                <li><i class="fas fa-check-circle"></i>100% royalties</li>
                                <li><i class="fas fa-check-circle"></i>100% satisfaction guarantee</li>
                        </ul>
                        <div class="package-button">
                            <a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Book Now <i class="fas fa-chevron-circle-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- pricing Section End -->
 <!-- testimonial Section Start -->
<section class="testimonial-sec all-section">
	<div class="container">
		<div class="row">
			<div class="col-lg-7 centerCol">
				<div class="sec-head wow bounceIn" data-wow-duration="2s">
					<h2>What Our Clients Say</h2>
					<p>Don't take our word for it – check out what our clients have to say about our services.</p>
				</div>
			</div>
			<div class="row">
				<div class="col-lg-6">
					<div class="testimonial-image wow fadeInLeft" data-wow-duration="2s">
						<img src="https://bookpublisheravenue.com/assets/img/macmillan-author-2.webp" data-src="https://bookpublisheravenue.com/assets/img/macmillan-author-2.webp" alt="" class="lazy">
						<!--<div class="testimonial-image-icon">-->
						<!--	<a href="#"> <img src="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%200%200'%3E%3C/svg%3E" data-src="assets/images/testimonial-image-icon.png" alt="img" class="lazy"> </a>-->
						<!--</div>-->
				</div>
			</div>
			<div class="col-lg-6">
				<div class="testi-slider">
					<div>
						<div class="testi-box wow fadeInRight" data-wow-duration="2s">
							<ul class="star">
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
							</ul>
							<p>I had the pleasure of working with <?php echo $bname?>' writing service in recent weeks, and I'm quite pleased with the end result. The writer I worked with had not just a unique mastery of language but also a great capacity to understand the details of my tale. From the moment of our initial conversation until the prompt delivery of the finished product, their professionalism was apparent. The collaborative approach significantly enhanced the creative process, and communication was flawless. To any writer looking for a professional and committed writing service for their literary requirements, I heartily suggest <?php echo $bname?>.</p>
							<ul class="testimonial-profile">
								<li>
									<img src="assets/images/profil-5.webp" data-src="assets/images/profil-5.webp" alt="" class="lazy">
								</li>
								<li>
									<h4>Cailin Brown</h4>
								</li>
							</ul>
						</div>
					</div>
					<div>
						<div class="testi-box wow fadeInRight" data-wow-duration="2s">
							<ul class="star">
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
							</ul>
							<p>I had a wonderful experience with <?php echo $bname?>. <?php echo $bname?> are very affordable, fast and effective compared to so many publishers that I have worked with so far.</p>
							<p>I love the staff too. In the course of publishing my book, Ben Miller and Terrance Cole greatly encouraged me mentally. They're so comprehensive, patient and encouraging. The editor is equally very smart , she has good ideas. Moreover, I love the fact that anytime I called, there was always someone to pick up my call and listen to me. I'm so blessed to have known <?php echo $bname?>. They believed in me and believed in my dream. They took me as a family. God bless.</p>
							<ul class="testimonial-profile">
								<li>
									<img src="assets/images/profil-6.webp" data-src="assets/images/profil-6.webp" alt="" class="lazy">
								</li>
								<li>
									<h4>Ashu Carole</h4>
								</li>
							</ul>
						</div>
					</div>
					<div>
						<div class="testi-box wow fadeInRight" data-wow-duration="2s">
							<ul class="star">
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
							</ul>
							<p>Well, if y'all out there are for real looking for the best publishing company, then <?php echo $bname?> is the one you need for your project. I am with <?php echo $bname?> till the end of time; therefore, thanks to everyone for this wonderful project. Ben Miller is the best project manager ever down here in New York City.</p>
							<ul class="testimonial-profile">
								<li>
									<img src="#" data-src="assets/images/profil-2.webp" alt="" class="lazy">
								</li>
								<li>
									<h4>Adellsonn</h4>
								</li>
							</ul>
						</div>
					</div>
					<div>
						<div class="testi-box wow fadeInRight" data-wow-duration="2s">
							<ul class="star">
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
							</ul>
							<!--<h3>Professional and knowledgeable team!</h3>-->
							<p>I've been really enjoying working with <?php echo $bname?>. Being a first-time author, there were a lot of moments when I felt lost and confused. Victor and Ben really supported me to keep believing in my work, guided me to keep going on the right track.</p>
							<p>They really helped me navigate my way in the publishing world especially the marketing part. After all, I really want to share the story with the world and it all happens through smart marketing. I would definitely work with <?php echo $bname?> again.</p>
							<ul class="testimonial-profile">
								<li>
									<img src="assets/images/profil-7.webp" data-src="assets/images/profil-7.webp" alt="" class="lazy">
								</li>
								<li>
									<h4>Kate Koo</h4>
								</li>
							</ul>
						</div>
					</div>
					<div>
						<div class="testi-box wow fadeInRight" data-wow-duration="2s">
							<ul class="star">
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
								<li><a href="javascript:void(0)"><i class="fas fa-star"></i></a></li>
							</ul>
							<p>I had a fantastic time experience working with a skilled ghostwriter from <?php echo $bname?>. Their experience for translating my ideas into what I had imagined was outstanding. They were receptive to my feedback although a bit time taking. I couldn't ask for a better literary partner.</p>
							<ul class="testimonial-profile">
								<li>
									<img src="assets/images/profil-4.webp" data-src="assets/images/profil-4.webp" alt="" class="lazy">
								</li>
								<li>
									<h4>Eric Holven</h4>
								</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div></section>
    <!-- testimonial Section End -->
  <!-- What We Do Section Start -->
<section class="we-do-sec">
	<div class="container-fluid">
		<div class="row">
			<div class="col-lg-8 col-md-12 centerCol">
				<div class="sec-head wow bounceIn" data-wow-duration="2s">
					<h2>Our Services</h2>
					<p>With our help, your manuscript will reach the right audience and stand out on the shelves.</p>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="we-do-slider sliderz">
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/1</h6>
							<div class="we-slider-image">
								<img src="assets/images/Book-Cover-Design.png" data-src="assets/images/Book-Cover-Design.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Book Cover Design</h5>
								</li>
								<li>
									<p>Don't judge a book by its cover, they say. But a well-designed cover instantly shows what the book is about.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInDown" data-wow-duration="2s">
							<h6>/2</h6>
							<div class="we-slider-image">
								<img src="assets/images/Book-Marketing.png" data-src="assets/images/Book-Marketing.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Book Marketing</h5>
								</li>
								<li>
									<p>We create simple yet powerful strategies to get your story to the right audience and help your book shine.</p>
								</li>
								<li>
									<a href="#" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/3</h6>
							<div class="we-slider-image">
								<img src="assets/images/Book-Editing.png" data-src="assets/images/Book-Editing.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Book Editing</h5>
								</li>
								<li>
									<p>With our book editing services, you can turn a good book into a great book with ease.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInDown" data-wow-duration="2s">
							<h6>/4</h6>
							<div class="we-slider-image">
								<img src="assets/images/Ghost-Writtng.png" data-src="assets/images/Ghost-Writtng.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Ghostwriting</h5>
								</li>
								<li>
									<p>No matter the genre, our ghostwriting team can help you create a masterpiece!</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/5</h6>
							<div class="we-slider-image">
								<img src="assets/images/Client-Endorsement.png" data-src="assets/images/Client-Endorsement.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Celebrity Endorsements</h5>
								</li>
								<li>
									<p>Imagine your project getting the star power it deserves – who better to make that happen than a celebrity?</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/6</h6>
							<div class="we-slider-image">
								<img src="assets/images/Book-Launch-Event.png" data-src="assets/images/Book-Launch-Event.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Book Launch Events</h5>
								</li>
								<li>
									<p>Elevate your book launch to new heights with our Book Launch Event Services! Make your debut unforgettable and launch your book with a bang.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/7</h6>
							<div class="we-slider-image">
								<img src="assets/images/Audio-Development.png" data-src="assets/images/Audio-Development.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Audiobook Development </h5>
								</li>
								<li>
									<p>We bring your words to life, making it easy for your audience to listen and enjoy.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/8</h6>
							<div class="we-slider-image">
								<img src="assets/images/Physical-Distribution.png" data-src="assets/images/Physical-Distribution.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Physical Distribution</h5>
								</li>
								<li>
									<p>From printing to reaching your doorstep, we ensure your books reach your audience efficiently.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/9</h6>
							<div class="we-slider-image">
								<img src="assets/images/Author-Website-Design.png" data-src="assets/images/Author-Website-Design.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Author Website Design</h5>
								</li>
								<li>
									<p>We create a simple, user-friendly space where readers can connect with you and explore your work.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/10</h6>
							<div class="we-slider-image">
								<img src="assets/images/Author-Branding.png" data-src="assets/images/Author-Branding.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Author Branding</h5>
								</li>
								<li>
									<p>From logo design to social media presence, we'll make sure your brand reflects the essence of your writing.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div>
					<div>
						<div class="we-slider-box wow fadeInUp" data-wow-duration="2s">
							<h6>/11</h6>
							<div class="we-slider-image">
								<img src="assets/images/Book-Publishing.png" data-src="assets/images/Book-Publishing.png" alt="" class="lazy ws-1">
							</div>
							<ul class="we-slid-list">
								<li>
									<h5>Book Publishing</h5>
								</li>
								<li>
									<p>We simplify the book publishing process, guiding you from editing to printing and getting your book into the world.</p>
								</li>
								<li>
									<a href="javascript:;" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn-1">Read More <i class="fas fa-chevron-circle-right"></i></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
				
			</div>
		</div>
	</div>
</section>
    <!-- What We Do Section End -->
    <!-- Frequently Asked Questions Start -->
<section>
	<div class="frequently-asked-questions-sec">
		<div class="container">
			<div class="row">
				<div class="col-lg-7 col-md-7 col-12">
					<div class="frequently-asked-questions-heading wow fadeInLeft" data-wow-duration="2s">
						<h2>Frequently Asked Questions</h2>
					</div>
					<div class="frequently-asked-questions-box">
						
						<div class="gorilla-content privacy-content ">
							<div class="accordion privacy-accr" id="accordionExample">
								<div class="accordion-item wow fadeInLeft" data-wow-duration="2s">
									<h2 class="accordion-header" id="headingOne">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
									
									WHAT DO I NEED TO PUBLISH A BOOK WITH <?php echo $bname?>?
									
									</button>
									</h2>
									<div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample" style="">
										<div class="accordion-body gorrila-page-txt   ">
											<p>You'll need a fully setup account (you've completed all steps of the account setup process) and your book's cover and interior files.</p>
											<p>You'll also need an ISBN for each format of your book if you'd like to make your book available for distribution. We can get your ISBN as you set up a title with us.</p>
										</div>
									</div>
								</div>
								<div class="accordion-item wow fadeInRight" data-wow-duration="2s">
									<h2 class="accordion-header" id="headingTwo">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
									
									WHAT IF I JUST WANT A FEEDBACK OR CONSULTATION ON MY BOOK?
									
									</button>
									</h2>
									<div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionExample" style="">
										<div class="accordion-body gorrila-page-txt   ">
											<p>Our editing and coaching services are perfectly suited for writers who are looking to improve their work. We can give input at whatever level of detail you'd like—feedback on the structure and concept of the book, critical review on the flow and transitions, or copyediting changes to the text itself. We can also address specific issues, as per your request, in our review of your work. If you recommend our editors will make the changes and improve the work for you.</p>
										</div>
									</div>
								</div>
								<div class="accordion-item  wow fadeInLeft" data-wow-duration="2s">
									<h2 class="accordion-header" id="headingthree">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsethree" aria-expanded="false" aria-controls="collapsethree">
									HOW INVOLVED WOULD I NEED TO BE IN THE PROCESS?
									</button>
									</h2>
									<div id="collapsethree" class="accordion-collapse collapse" aria-labelledby="headingthree" data-bs-parent="#accordionExample" style="">
										<div class="accordion-body gorrila-page-txt   ">
											<p>Your level of involvement is entirely up to you. You can opt to work closely with your ghostwriter, or simply provide them with basic information and let them do the rest of the work. This is your book, which means that you have final say on the content and it's your decision how and when you give your input and approval. We'll work with you to customize the process so that it fits your schedule and preferred working style. Although in an autobiography/memoir it is highly recommended to work side by side with your ghostwriter.</p>
										</div>
									</div>
								</div>
								<div class="accordion-item wow fadeInRight" data-wow-duration="2s">
									<h2 class="accordion-header" id="headingfour">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsefour" aria-expanded="false" aria-controls="collapsefour">
									HOW LONG DOES <?php echo $bname?> PROCESS NORMALLY TAKE?
									</button>
									</h2>
									<div id="collapsefour" class="accordion-collapse collapse" aria-labelledby="headingfour" data-bs-parent="#accordionExample" style="">
										<div class="accordion-body gorrila-page-txt   ">
											<p>The duration of the <?php echo $bname?> process is partly up to you—the author! Depending on your schedule and desired level of involvement, your ghostwriter will develop material for you to review and approve on a weekly or biweekly basis.</p>
										</div>
									</div>
								</div>
								
								<div class="accordion-item wow fadeInRight" data-wow-duration="2s">
									<h2 class="accordion-header" id="headingfive">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsefive" aria-expanded="false" aria-controls="collapsefive">
									ARE YOUR SERVICES 100% CONFIDENTIAL?
									</button>
									</h2>
									<div id="collapsefive" class="accordion-collapse collapse" aria-labelledby="headingfive" data-bs-parent="#accordionExample" style="">
										<div class="accordion-body gorrila-page-txt   ">
											<p>Yes, of course! Our services are 100% confidential. We will never use any of the information you provide us for any other purpose, nor will we use the writing developed for your book ever again. We have a very comprehensive privacy policy posted on our website, and we are also happy to send you a non-disclosure agreement. If you are happy and with your consent, you can leave a testimonial and we will use it on the website.</p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-5 col-md-5 col-12">
					<div class="frequently-asked-questions-form">
						<div class="frequently-asked-questions-form-heading">
							<h2><span>50%</span> OFF</h2>
							<p>ON BOOK PUBLICATION <br> SERVICES</p>
						</div>
						<form class="form-get-quote"  action="<?php echo $post_url; ?>" method="post">
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
								
								<input type="hidden" name="subject" value="Popup Form (<?php echo $url ?>)">
								<input type="hidden" name="source" value="<?php echo $lead_source ?>" />
							<div class="freq-que-form-input">
								<input type="text" name="name" placeholder="Your Name" required=""
									value="<?php echo !empty($_POST['name']) ? $_POST['name'] : '' ?>">
								<?php echo !empty($error['name']) ? $error['name'] : ''; ?>
								<div class="freq-que-form-input-icon">
									<i class="far fa-user"></i>
								</div>
							</div>
							<div class="freq-que-form-input">
								<input type="text" name="phone" minlength="10" maxlength="12"
									placeholder="Your Phone"
									value="<?php echo !empty($_POST['phone']) ? $_POST['phone'] : '' ?>"
									required="required">
								<?php echo !empty($error['phone']) ? $error['phone'] : ''; ?>
								<div class="freq-que-form-input-icon">
									<i class="fas fa-phone-alt"></i>
								</div>
							</div>
							<div class="freq-que-form-input">
								<input type="email" name="email" placeholder="Your Email"
									value="<?php echo !empty($_POST['email']) ? $_POST['email'] : '' ?>"
									required="required">
								<?php echo !empty($error['email']) ? $error['email'] : ''; ?>
								<div class="freq-que-form-input-icon">
									<i class="fas fa-envelope"></i>
								</div>
							</div>
							<div class="freq-que-form-input">
								<textarea autocomplete="nope" name="message" rows="4"
									placeholder="Enter Brief"><?php echo !empty($_POST['message']) ? $_POST['message'] : '' ?></textarea>
								<?php echo !empty($error['message']) ? $error['message'] : ''; ?>
								<div class="freq-que-form-input-icon chg">
									<i class="fas fa-paper-plane"></i>
								</div>
							</div>
							<div class="freq-que-form-button">
								<button class="btn-1" cite="Submit" data-hover="Submit" type="submit" name="cta1"
									value="Submit Now">SUBMIT <i class="fas fa-chevron-circle-right" aria-hidden="true"></i></button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
    <!-- Frequently Asked Questions End -->
 <!-- Footer Start -->

<footer>
	<div class="footer-sec">
		<div class="container">
			<div class="row">
				<div class="col-lg-3 col-md-3 col-12">
					<div class="ftr-about wow fadeInUp" data-wow-duration="2s">
						<h2>About Us</h2>
						<p>Established in 2016, <?php echo $bname?> was created to address an unmet need: a comprehensive solution for authors covering writing, editing, printing, publishing, distribution, and marketing – all under one roof.</p>
						<img src="assets/images/ftr-card.webp" alt="img">
					</div>
				</div>
				<div class="col-lg-3 col-md-3 col-12">
					<div class="ftr-quick-link wow fadeInDown" data-wow-duration="2s">
						<h2>Quick Links</h2>
						<ul>
							<li><a target="_blank" href="https://bookpublisheravenue.com/terms-conditions">Terms &amp; Condition</a></li>
							<li><a target="_blank" href="https://bookpublisheravenue.com/privacy-policy">Privacy Policy</a></li>							
						</ul>
					</div>
				</div>
				<div class="col-lg-3 col-md-3 col-12">
					<div class="ftr-address wow fadeInUp" data-wow-duration="2s">
						<h2>Mailing Address</h2>
						<ul>
							<li><i class="fas fa-map-marker-alt"></i><a href="javascript:;"><span><?php echo $add?></span></a></li>
						</ul>						
					</div>
				</div>
				<div class="col-lg-3 col-md-3 col-12">
					<div class="ftr-address contact wow fadeInDown" data-wow-duration="2s">
						<h2>Contact Info</h2>
						<ul>
							<li><i class="fas fa-envelope"></i><a href="mailto:info@<?php echo $domainname?>">info@<?php echo $domainname?></a></li>
							<li><i class="fas fa-phone-alt"></i><a href="tel:<?php echo $no?>"><span><?php echo $no?></span></a></li>
						</ul>
						
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="copy-right">
		<div class="container">
			<div class="row">
				<div class="col-lg-6 col-md-6 col-12 wow fadeInLeft" data-wow-duration="2s">
					<!--<ul>-->
					<!--	<li><a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a></li>-->
					<!--	<li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>-->
					<!--	<li><a href="#" target="_blank"><i class="fab fa-linkedin-in"></i></a></li>						-->
					<!--</ul>-->
				</div>
				<div class="col-lg-5 col-md-5 col-12">
					<div class="copy-right-text wow fadeInRight" data-wow-duration="2s">
						<p>©2025 <?php echo $bname?>, All Rights Reserved. </p>
					</div>
				</div>
				
			</div>
		</div>
		<div class="copy-right-button">
			<a href="#"><i class="fas fa-long-arrow-alt-up"></i></a>
		</div>
	</div>
</footer>


<div class="floatbutton">
  <div class="btns_wrap">
    <a href="tel:<?php echo $no?>" class="call_wrap m_bhar">
      <span class="icoo"><i class="fa fa-phone"></i></span>
      <span> <?php echo $no?></span>
    </a>
  </div>
  <div class="btns_wrap">
    <a href="javascript:;" onclick="LiveChatWidget.call('maximize')" class="call_wrap m_bhar">
      <span class="icoo"><i class="fa fa-comment"></i></span>
      <span> Chat Now</span>
    </a>
  </div>
</div>

<div class="modal fade p-0" tabindex="-1" id="exampleModal">
	<div class="modal-md modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<button type="button" class="btn-close btn-close-ctm" data-bs-dismiss="modal" aria-label="Close">
			</button>
			<div class="modal-body">
				<div class="logo-pop text-center mb-2 mt-3">
					<img src="assets/images/logo.webp" data-src="assets/images/logo.webp" alt="Amazon Publishing Partners" class="lazy">
				</div>
				<!-- Form Start -->
				<form class="form-get-quote" action="<?php echo $post_url; ?>" method="post">
					<div class="header-form">
						<div class="row">
							<div class="col-lg-12 mb-2">
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
								
								<input type="hidden" name="subject" value="Popup Form (<?php echo $url ?>)">
								<input type="hidden" name="source" value="<?php echo $lead_source ?>" />
								<div class="form-group">
									<input ttype="text" name="name" placeholder="Your Name" required=""
									value="<?php echo !empty($_POST['name']) ? $_POST['name'] : '' ?>">
								<?php echo !empty($error['name']) ? $error['name'] : ''; ?>
									<input type="email" name="email" placeholder="Your Email"
									value="<?php echo !empty($_POST['email']) ? $_POST['email'] : '' ?>"
									required="required">
								<?php echo !empty($error['email']) ? $error['email'] : ''; ?>
									<input class="phone" type="text" name="phone" minlength="10" maxlength="12"
									placeholder="Your Phone"
									value="<?php echo !empty($_POST['phone']) ? $_POST['phone'] : '' ?>"
									required="required">
								<?php echo !empty($error['phone']) ? $error['phone'] : ''; ?>
									<textarea autocomplete="nope" name="message" rows="4"
									placeholder="Enter Brief"><?php echo !empty($_POST['message']) ? $_POST['message'] : '' ?></textarea>
								<?php echo !empty($error['message']) ? $error['message'] : ''; ?>
								</div>
							</div>
							<div class="btn-part">
								<div class="form-group mt-2 text-center">
									<button class="btn-1" cite="Submit" data-hover="Submit" type="submit" name="cta1"
									value="Submit Now">SUBMIT NOW</button>
								</div>
							</div>
						</div>
					</div></form>
					<!-- Form End -->
				</div>
			</div>
		</div>
	</div>
  <!-- Popupform -->


    <!-- Footer End -->

<script src="assets/js/jquery-3.6.0.min.js"></script>
<!-- <script r-src="assets/js/wow.js"></script> -->
<script src="assets/slick/slick.js"></script>
<script src="assets/slick/slick.min.js"></script>
<script src="assets/js/jquery.slicknav.js"></script>
<!-- <script r-src="assets/js/fancybox.js"></script> -->
<script src="assets/js/bootstrap.js"></script>
<script src="assets/js/custom.js"></script>
<!-- <script r-src="fontawesome5/font-awesomejs/font.js"></script> -->
<!-- <script r-src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js"></script>
<script>
    	$("document").ready(function(){
  setTimeout(function(){
    $("#exampleModal").modal("show");
  },3000);
});
</script>
<?php require '../inc/chat.php';?>


