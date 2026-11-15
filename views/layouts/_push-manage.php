<!-- ABILITA PUSH -->
<div class="modal fade " id="pushEnableModal" tabindex="-1" role="dialog" aria-labelledby="pushEnableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content ">
			<div class="modal-header">
				<h3 class="modal-title" id="pushEnableModalLabel"><?php echo Yii::t('app','Push Notifications');?></h3>
			</div>
			<div class="modal-body ">
                <p class="lead">
                    <h4><?php echo Yii::t('app','Enabling');?>:</h4>
                    <?php echo Yii::t('app','By enabling this setting you will receive <b> push </b> notifications when there are new transactions on your wallet. ');?>
                    <i><?php echo Yii::t('app','Notifications are enabled for each device. To receive notifications on other devices you need to log in from each one, enable it and make sure you are online.');?>
					</i>
                </p>
                <p class="lead text-danger"><?php echo Yii::t('app','Be sure to reply <b>Allow </b>when prompted');?></p>
                <hr>
                <p class="lead">
                    <h4><?php echo Yii::t('app','Disabling');?>:</h4>
                    <?php echo Yii::t('app','By disabling <b> push </b> notifications, you will no longer receive messages when there are transactions on your wallet.');?> </b>
					<i> <?php echo Yii::t('app','Disabling push notifications from this device may also eliminate the subscription of any other connected devices.');?> </i>
                </p>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<button type="button" class="btn btn-secondary js-push-btn-modal-back" data-bs-dismiss="modal" >
						<i class="fa fa-arrow-left me-1" aria-hidden="true"></i> <?php echo Yii::t('app','back');?>
					</button>
				<button type="button" class="js-push-btn btn btn-primary " data-bs-dismiss="modal" >
						<i class="fa fa-check me-1" aria-hidden="true"></i> <?php echo Yii::t('app','confirm');?>
					</button>
				<button type="button" class="js-push-btn-remove btn btn-primary " data-bs-dismiss="modal">
						<i class="fa fa-times me-1" aria-hidden="true"></i> <?php echo Yii::t('app','remove');?>
					</button>
			</div>
		</div>
	</div>
</div>
