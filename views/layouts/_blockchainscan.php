<!-- show Blockchain Scan modal dialog-->
<div class="modal fade" id="blockchainScanModal" tabindex="-1" role="dialog" aria-labelledby="blockchainScanModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-danger">
			<div class="modal-header">
				<h3 class="modal-title" id="blockchainScanModalLabel"><?php echo Yii::t('app','Blockchain Reset');?></h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="resetSliderAriablockchain"></button>
			</div>
			<div class="modal-body">
				<p><?php echo Yii::t('app','WARNING - This operation tries to restore the transactions history. Scanning may consume a lot of memory and slow down normal phone activity.');?></p>
				<p>
					<?php echo Yii::t('app','Are you sure to continue?');?>
				</p>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<button type="button" class="blockchainScanSliderBack2 btn btn-secondary" data-bs-dismiss="modal" >
						<i class="fa fa-arrow-left me-1" aria-hidden="true"></i> <?php echo Yii::t('app','Back');?>
					</button>
					<button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="showblockchainScan" data-bs-toggle="modal" data-bs-target="#showblockchainScanModal">
						<i class="fa fa-check me-1" aria-hidden="true"></i> <?php echo Yii::t('app','Confirm');?>
					</button>
			</div>
		</div>
	</div>
</div>
