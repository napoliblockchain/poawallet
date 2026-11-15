<?php

use yii\helpers\Html;
use yii\helpers\Url;
use app\components\UserRole;

include('manage-blockchainscan_js.php');


$this->title = Yii::t('app', 'Settings');
$links = [
    [['/network/index'], 'fas fa-info-circle', Yii::t('app', 'Network info')],
    [['/nodes/update'], 'fas fa-network-wired', Yii::t('app', 'Select nodes')],
    [['/smartcontracts/index'], 'fas fa-star', Yii::t('app', 'Manage tokens')],
    [['/blockchains/index'], 'fas fa-link', Yii::t('app', 'Manage networks')],
];
if (UserRole::isWebmaster()) {
    $links[] = [['/standard-blockchain-values/index'], 'fas fa-database', Yii::t('app', 'Standard networks')];
    $links[] = [['/standard-smart-contract-values/index'], 'fas fa-cubes', Yii::t('app', 'Standard tokens')];
}
?>
<div class="card mb-3">
    <div class="card-header"><h2 class="card-title"><?= Html::encode($this->title) ?></h2></div>
    <div class="list-group list-group-flush">
        <?php foreach ($links as [$url, $icon, $label]): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= Url::to($url) ?>">
                <i class="<?= $icon ?> fa-fw text-primary" aria-hidden="true"></i><span class="flex-fill"><?= Html::encode($label) ?></span><i class="fas fa-chevron-right text-secondary" aria-hidden="true"></i>
            </a>
        <?php endforeach ?>
    </div>
</div>

<div class="card border-danger">
    <div class="card-header"><h3 class="card-title text-danger"><?= Html::encode(Yii::t('app', 'Experimental')) ?></h3></div>
    <div class="list-group list-group-flush">
        <div class="list-group-item d-flex align-items-center gap-3">
            <i class="fas fa-eraser fa-fw text-danger" aria-hidden="true"></i>
            <span class="flex-fill text-danger"><?= Html::encode(Yii::t('app', 'Reset blockchain')) ?></span>
            <div class="blockchainRescan sweet-check" role="switch" aria-checked="false" aria-label="<?= Html::encode(Yii::t('app', 'Reset blockchain')) ?>"><div class="outline"><span></span></div></div>
        </div>
    </div>
</div>
