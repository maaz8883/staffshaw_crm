<section class="pt-5 pb-md-4 portfolio">
		<div class="container-xxl">
			<div class="row text-center justify-content-center">
				<div class="col-md-10">
					<h2 class="f-40 clr-1 fw-600">See Orbit Book Publishers Recent Work</h2>
					<p class="f-20 fw-500">Explore a small part of our portfolio of published books. Our book experts have created numerous successful books across various genres. See the quality and creativity that sets our services apart.</p>
				</div>
			</div>
		</div>
		<div class="container-fluid pb-5 pt-3">
			<div class="portfolio-slider">
				<?php $dirname = "assets/img/portfolio/"; $images = glob($dirname . "*.webp");  ?>
				<?php foreach ($images as $image)  :?>
					<?php $title = pathinfo($image); ?>
					<div class="item">
						<div class="gallery">
							<img data-lazy="<?= $image ?>" alt="<?= $title['filename']?> by <?= $bname?>"/>
						</div>
					</div>
				<?php endforeach ?>
			</div>
			
		</div>
	</section>