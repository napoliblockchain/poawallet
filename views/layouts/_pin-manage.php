<?php

use yii\helpers\Html;

/* Gestione del PIN dal profilo (manage-pin_js.php, pincode-settings.js): finestre a schermo intero. */
?>
<!-- nuovo PIN -->
<div class="modal fade wallet-pin-modal" id="pinNewModal" tabindex="-1" aria-labelledby="pinNewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body">
                <div class="wallet-pin-head">
                    <i class="fas fa-key" aria-hidden="true"></i>
                    <h2 class="modal-title" id="pinNewModalLabel"><?= Html::encode(Yii::t('app', 'New PIN')) ?></h2>
                    <p class="text-secondary"><?= Html::encode(Yii::t('app', 'Choose a 5-digit PIN.')) ?></p>
                </div>
                <input type="hidden" id="pin_password" readonly="readonly">
                <div class="pin-numpad pin-newframe-numpad"></div>
                <div class="wallet-pin-actions">
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal" id="pinNewButtonBack"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'back')) ?></button>
                    <button type="button" disabled="disabled" class="btn btn-primary btn-lg disabled" id="pinNewButton"><i class="fa fa-check me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'confirm')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- verifica nuovo PIN -->
<div class="modal fade wallet-pin-modal" id="pinVerifyModal" tabindex="-1" aria-labelledby="pinVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body">
                <div class="wallet-pin-head">
                    <i class="fas fa-redo" aria-hidden="true"></i>
                    <h2 class="modal-title" id="pinVerifyModalLabel"><?= Html::encode(Yii::t('app', 'PIN Verify')) ?></h2>
                    <p class="text-secondary"><?= Html::encode(Yii::t('app', 'Enter the same PIN again.')) ?></p>
                </div>
                <input type="hidden" id="pin_password_confirm" readonly="readonly">
                <div class="pin-confirm-numpad pin-newframe-numpad"></div>
                <div class="wallet-pin-actions">
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal" id="pinVerifyButtonBack"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'back')) ?></button>
                    <button type="button" disabled="disabled" class="btn btn-primary btn-lg disabled" id="pinVerifyButton"><i class="fa fa-check me-2" aria-hidden="true"></i><span id="pinVerifyButtonText"><?= Html::encode(Yii::t('app', 'confirm')) ?></span></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- rimozione PIN -->
<div class="modal fade wallet-pin-modal" id="pinRemoveModal" tabindex="-1" aria-labelledby="pinRemoveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body">
                <div class="wallet-pin-head">
                    <i class="fas fa-unlock-alt" aria-hidden="true"></i>
                    <h2 class="modal-title" id="pinRemoveModalLabel"><?= Html::encode(Yii::t('app', 'Remove PIN')) ?></h2>
                    <p class="text-secondary"><?= Html::encode(Yii::t('app', 'Enter your PIN to remove it.')) ?></p>
                </div>
                <div class="pin-remove-numpad pin-newframe-numpad"></div>
                <div class="wallet-pin-actions">
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal" id="pinRemoveButtonBack"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'back')) ?></button>
                    <button type="button" disabled="disabled" class="btn btn-primary btn-lg disabled" id="pinRemoveButton"><i class="fa fa-check me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'confirm')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
