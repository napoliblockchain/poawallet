<!--  nuovo PIN -->
<div class="modal fade " id="pinNewModal" tabindex="-1" role="dialog" aria-labelledby="pinNewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title"><?php echo Yii::t('app','New PIN');?></h3>
			</div>
			<div class="modal-body ">
				<input type='hidden' id='pin_password' class='form-control' readonly="readonly"/>
        		<div class="pin-numpad pin-newframe-numpad"></div>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<button type="button" class="btn btn-secondary text-capitalize" data-bs-dismiss="modal" id="pinNewButtonBack">
						<i class="fa fa-arrow-left me-1" aria-hidden="true"></i> <?php echo Yii::t('app','back');?>
					</button>
				<button type="button" disabled="disabled" class="btn btn-primary disabled  text-capitalize" id="pinNewButton">
						<i class="fa fa-check me-1" aria-hidden="true"></i> <?php echo Yii::t('app','confirm');?>
					</button>
			</div>
		</div>
	</div>
</div>
<!-- VERIFICA nuovo PIN -->
<div class="modal fade " id="pinVerifyModal" tabindex="-1" role="dialog" aria-labelledby="pinVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="pinVerifyModalLabel"><?php echo Yii::t('app','PIN Verify');?></h3>
			</div>
			<div class="modal-body ">
				<input type='hidden' id='pin_password_confirm' class='form-control' readonly="readonly"/>
        		<div class="pin-confirm-numpad pin-newframe-numpad"></div>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<button type="button" class="btn btn-secondary text-capitalize" data-bs-dismiss="modal" id="pinVerifyButtonBack">
						<i class="fa fa-arrow-left me-1" aria-hidden="true"></i> <?php echo Yii::t('app','back');?>
					</button>
				<button type="button" disabled="disabled" class="btn btn-primary text-capitalize disabled" id="pinVerifyButton">
						<i class="fa fa-check me-1" aria-hidden="true"></i> <span id='pinVerifyButtonText'><?php echo Yii::t('app','confirm');?></span>
					</button>
			</div>
		</div>
	</div>
</div>

<!-- RIMUOVI PIN -->
<div class="modal fade " id="pinRemoveModal" tabindex="-1" role="dialog" aria-labelledby="pinRemoveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="pinRemoveModalLabel"><?php echo Yii::t('app','Remove PIN');?></h3>
			</div>
			<div class="modal-body ">
        		<div class="pin-remove-numpad pin-newframe-numpad"></div>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<button type="button" class="btn btn-secondary " data-bs-dismiss="modal" id="pinRemoveButtonBack" >
						<i class="fa fa-arrow-left me-1" aria-hidden="true"></i> <?php echo Yii::t('app','back');?>
					</button>
				<button type="button" disabled="disabled" class="btn btn-primary disabled" id="pinRemoveButton" >
						<i class="fa fa-check me-1" aria-hidden="true"></i> <?php echo Yii::t('app','confirm');?>
					</button>
			</div>
		</div>
	</div>
</div>
