<!-- RICHIESTA PIN da MAIN Layout-->
<div class="modal fade " id="pinRequestModal" tabindex="-1" role="dialog" aria-labelledby="pinRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="pinRequestModalLabel"><?php echo Yii::t('app','PIN Request');?></h3>
            </div>
            <div class="modal-body ">
                
                    <input type='hidden' id='pin_password' class='form-control' readonly="readonly"/>
                    <input type='hidden' id='pin_password_confirm' class='form-control' readonly="readonly"/>
                
                <div class="pin-confirm-numpad pin-newframe-numpad"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
				<button type="button" disabled="disabled" class="btn btn-primary disabled " id="pinRequestButton">
                        <i class="fa fa-check me-1" aria-hidden="true"></i> <span id="#pinRequestButtonText"><?php echo Yii::t('app','Confirm');?></span>
                    </button>
            </div>
        </div>
    </div>
</div>
