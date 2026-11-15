<?php

use yii\helpers\Html;

/* Pannello notifiche: contenuto da notifications-MainWorker.js, apertura/chiusura da global.script.js (data-search). */
?>
<div class="search-form" role="dialog" aria-modal="true" aria-label="<?= Html::encode(Yii::t('app', 'Notifications')) ?>">
    <div class="card wallet-notifications mx-3 mx-sm-auto">
        <div class="card-header">
            <h3 class="card-title"><?= Html::encode(Yii::t('app', 'Notifications')) ?></h3>
            <div class="card-actions">
                <button type="button" class="btn-close close-search-form" data-search="close" aria-label="<?= Html::encode(Yii::t('app', 'Close')) ?>"></button>
            </div>
        </div>
        <ul class="notification-list list-unstyled mb-0" id="notification-list">
            <li>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center"><div class="coin-name"></div></div>
                    <div class="notify-readAll"></div>
                </div>
            </li>
        </ul>
    </div>
</div>
