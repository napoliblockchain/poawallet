<!-- modal di copia in clipboard -->
<div class="modal fade" id="copyAddressModal" tabindex="-1" role="dialog" aria-labelledby="copyAddressModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="copyAddressModalLabel"><?php echo Yii::t('app','Message');?></h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<?php echo Yii::t('app','Address copied in clipboard.');?>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
					<?php echo Yii::t('app','Close');?>
				</button>
			</div>
		</div>
	</div>
</div>
