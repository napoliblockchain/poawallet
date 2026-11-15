<!-- show Master Seed -->
<div class="modal fade" id="showMasterSeedModal" tabindex="-1" role="dialog" aria-labelledby="showMasterSeedModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-danger">
			<div class="modal-header">
				<h3 class="modal-title" id="showMasterSeedModalLabel"><?php echo Yii::t('app','Master Seed');?></h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="resetSliderAriaMaster1"></button>
			</div>
			<div class="modal-body">
				<p id='masterSeed' class="alert alert-info fw-bold font-monospace no-copypaste"></p>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<button type="button" class="masterseedSliderBack3 btn btn-secondary" data-bs-dismiss="modal" >
						<?php echo Yii::t('app','Close');?>
					</button>
			</div>
		</div>
	</div>
</div>

<!-- show Master Seed modal dialog-->
<div class="modal fade" id="masterSeedModal" tabindex="-1" role="dialog" aria-labelledby="masterSeedModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-danger">
			<div class="modal-header">
				<h3 class="modal-title" id="masterSeedModalLabel"><?php echo Yii::t('app','Backup Master Seed');?></h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="resetSliderAriaMaster2"></button>
			</div>
			<div class="modal-body">
				<div class="masterSeedMessagePinEnabled" style="display:none;">
	                <p><?php echo Yii::t('app','WARNING - It is dangerous to show your Master Seed. Continue only if it is necessary because you have lost your previous backup.');?></p>
					<p>
						<?php echo Yii::t('app','Are you sure to continue?');?>
					</p>
					<div class="mb-3">
						<label class="form-label" for="masterSeedPassword"><?php echo Yii::t('app','Wallet password');?></label>
						<input type="password" id="masterSeedPassword" class="form-control" autocomplete="current-password">
					</div>
				</div>
				<div class="masterSeedMessagePinDisabled" style="display:none;">
	                <p><?php echo Yii::t('app','WARNING - You can backup your seed only if secure PIN is enabled.');?></p>
				</div>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<div class="masterSeedMessagePinEnabled" style="display:none;">
				<button type="button" class="masterseedSliderBack2 btn btn-secondary" data-bs-dismiss="modal" >
							<i class="fa fa-arrow-left me-1" aria-hidden="true"></i> <?php echo Yii::t('app','back');?>
						</button>
						<button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="showMasterSeed" data-bs-toggle="modal" data-bs-target="#showMasterSeedModal">
							<i class="fa fa-check me-1" aria-hidden="true"></i> <?php echo Yii::t('app','confirm');?>
						</button>
				</div>
				<div class="masterSeedMessagePinDisabled" style="display:none;">
				<button  type="button" class="masterseedSliderBack1 btn btn-secondary" data-bs-dismiss="modal">
							<?php echo Yii::t('app','Close');?>
						</button>
				</div>
			</div>
		</div>
	</div>
</div>
