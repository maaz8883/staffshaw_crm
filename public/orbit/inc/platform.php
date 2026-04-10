<section class="py-5 platforms">
		<div class="container-xl">
			<div class="slider">
				<?php $dirname = "assets/img/feature/"; $images = glob($dirname . "*.webp");  ?>
				<?php foreach ($images as $image)  :?>
					<?php $title = pathinfo($image); ?>
					<div class="item ">
						<div class="platform">
							<img data-lazy="<?= $image ?>" alt="<?= $title['filename']?> by <?= $bname?>"/>
						</div>
					</div>
				<?php endforeach ?>
			</div>
		</div>
</section>
