<?php

use yii\helpers\Html;

/* Master seed: PIN a schermo intero (manage-masterseed_js.php), poi le 12 parole. Senza PIN impostato non si legge. */
?>
<div class="modal fade wallet-pin-modal" id="masterSeedModal" tabindex="-1" aria-labelledby="masterSeedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body">
                <div class="wallet-pin-head">
                    <i class="fas fa-key" aria-hidden="true"></i>
                    <h2 class="modal-title" id="masterSeedModalLabel"><?= Html::encode(Yii::t('app', 'Backup Master Seed')) ?></h2>
                </div>
                <div class="masterSeedMessagePinEnabled w-100" style="display:none;">
                    <div class="alert alert-warning mx-auto mb-0" style="max-width: 22rem;" role="alert">
                        <?= Html::encode(Yii::t('app', 'WARNING - It is dangerous to show your Master Seed. Continue only if it is necessary because you have lost your previous backup.')) ?>
                        <strong class="d-block mt-1"><?= Html::encode(Yii::t('app', 'Enter your PIN: after 3 wrong attempts the seed is locked for 24 hours.')) ?></strong>
                    </div>
                </div>
                <div class="masterSeedMessagePinEnabled pin-seed-numpad w-100 d-flex justify-content-center" style="display:none;"></div>
                <p class="text-danger fw-bold text-center mb-0" id="masterSeedError" role="alert"></p>
                <div class="masterSeedMessagePinEnabled wallet-pin-actions" style="display:none;">
                    <button type="button" class="masterseedSliderBack2 btn btn-outline-secondary btn-lg" data-bs-dismiss="modal" id="resetSliderAriaMaster2"><i class="fa fa-arrow-left me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Back')) ?></button>
                    <button type="button" class="btn btn-danger btn-lg" id="showMasterSeed" disabled><i class="fa fa-eye me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Show')) ?></button>
                </div>
                <div class="masterSeedMessagePinDisabled text-center" style="display:none;">
                    <p class="lead"><?= Html::encode(Yii::t('app', 'WARNING - You can backup your seed only if secure PIN is enabled.')) ?></p>
                    <button type="button" class="masterseedSliderBack1 btn btn-primary btn-lg" data-bs-dismiss="modal"><?= Html::encode(Yii::t('app', 'Close')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade wallet-pin-modal" id="showMasterSeedModal" tabindex="-1" aria-labelledby="showMasterSeedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body">
                <div class="wallet-pin-head">
                    <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    <h2 class="modal-title" id="showMasterSeedModalLabel"><?= Html::encode(Yii::t('app', 'Master Seed')) ?></h2>
                    <p class="text-secondary mb-0"><?= Html::encode(Yii::t('app', 'Write these 12 words on paper, in this order, and keep them in a safe place.')) ?></p>
                </div>
                <ol id="masterSeed" class="wallet-seed-words no-copypaste" aria-live="polite"></ol>
                <div class="wallet-pin-actions">
                    <button type="button" class="masterseedSliderBack3 btn btn-primary btn-lg" data-bs-dismiss="modal" id="resetSliderAriaMaster1"><?= Html::encode(Yii::t('app', 'Close')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
