<?php

use yii\helpers\Html;

/* Blocco con PIN dopo l'inattività: schermo intero, non si chiude senza il PIN corretto (pincode-global.js). */
?>
<div class="modal fade wallet-pin-modal" id="pinRequestModal" tabindex="-1" aria-labelledby="pinRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body">
                <div class="wallet-pin-head">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <h2 class="modal-title" id="pinRequestModalLabel"><?= Html::encode(Yii::t('app', 'PIN Request')) ?></h2>
                    <p class="text-secondary"><?= Html::encode(Yii::t('app', 'Enter your PIN to unlock the wallet.')) ?></p>
                </div>
                <input type="hidden" id="pin_password" readonly="readonly">
                <input type="hidden" id="pin_password_confirm" readonly="readonly">
                <div class="pin-confirm-numpad pin-newframe-numpad"></div>
                <div class="wallet-pin-actions">
                    <button type="button" disabled="disabled" class="btn btn-primary btn-lg disabled" id="pinRequestButton">
                        <i class="fa fa-unlock me-2" aria-hidden="true"></i><span id="pinRequestButtonText"><?= Html::encode(Yii::t('app', 'Confirm')) ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
