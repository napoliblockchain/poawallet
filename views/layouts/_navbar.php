<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\bootstrap4\NavBar; // needed to load bootstrap4 assets
use app\components\UserRole;

// Ensure bootstrap4 assets are loaded.
NavBar::begin();
NavBar::end();

$route = Yii::$app->requestedRoute ?? '';
$active = function (string $prefix) use ($route): string {
    return str_starts_with($route, ltrim($prefix, '/')) ? 'active' : '';
};

?>

<div class="nav-menu" style="display: none;">
    <nav class="menu">

        <div class="nav-container">
            <ul class="main-menu">
                <li class="<?= $active('/wallet') ?>">
                    <a href="<?= Url::to(['/wallet/index']) ?>">
                        <i class="fas fa-wallet fa-lg text-primary"></i>
                        <strong class="special"><?= Yii::t('app', 'My Wallet') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/send') ?>">
                    <a href="<?= Url::to(['/send/index']) ?>">
                        <i class="far fa-paper-plane fa-lg text-primary"></i>
                        <strong class="special"><?= Yii::t('app', 'Send') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/receive') ?>">
                    <a href="<?= Url::to(['/receive/index']) ?>">
                        <i class="fas fa-download fa-lg text-primary"></i>
                        <strong class="special"><?= Yii::t('app', 'Receive') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/transactions') ?>">
                    <a href="<?= Url::to(['/transactions/index']) ?>">
                        <i class="fa fa-list fa-lg text-primary"></i>
                        <strong class="special"><?= Yii::t('app', 'Transactions') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/mailbox') ?>">
                    <a href="<?= Url::to(['/mailbox/inbox']) ?>">
                        <i class="fa fa-envelope fa-lg text-primary"></i>
                        <strong class="special"><?= Yii::t('app', 'Mailbox') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/settings') ?>">
                    <a href="<?= Url::to(['/settings/index']) ?>">
                        <i class="fa fa-network-wired fa-lg text-primary"></i>
                        <strong class="special"><?= Yii::t('app', 'Network') ?></strong>
                    </a>
                </li>

                <!-- Step 1.2 admin/network entrypoints (tokenpay-compatible routes). -->
                <li class="<?= $active('/nodes') ?>">
                    <a href="<?= Url::to(['/nodes/update']) ?>">
                        <i class="fa fa-project-diagram fa-lg text-info"></i>
                        <strong class="special"><?= Yii::t('app', 'Select nodes') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/smartcontracts') ?>">
                    <a href="<?= Url::to(['/smartcontracts/index']) ?>">
                        <i class="fa fa-star fa-lg text-info"></i>
                        <strong class="special"><?= Yii::t('app', 'Manage tokens') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/blockchains') ?>">
                    <a href="<?= Url::to(['/blockchains/index']) ?>">
                        <i class="fa fa-link fa-lg text-info"></i>
                        <strong class="special"><?= Yii::t('app', 'Manage networks') ?></strong>
                    </a>
                </li>
                <?php if (!Yii::$app->user->isGuest && UserRole::isWebmaster()): ?>
                <li class="<?= $active('/standard-blockchain-values') ?>">
                    <a href="<?= Url::to(['/standard-blockchain-values/index']) ?>">
                        <i class="fa fa-database fa-lg text-secondary"></i>
                        <strong class="special"><?= Yii::t('app', 'Standard networks') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/standard-smart-contract-values') ?>">
                    <a href="<?= Url::to(['/standard-smart-contract-values/index']) ?>">
                        <i class="fa fa-cubes fa-lg text-secondary"></i>
                        <strong class="special"><?= Yii::t('app', 'Standard tokens') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/profiles') ?>">
                    <a href="<?= Url::to(['/profiles/index']) ?>">
                        <i class="fa fa-id-badge fa-lg text-warning"></i>
                        <strong class="special"><?= Yii::t('app', 'Profiles') ?></strong>
                    </a>
                </li>
                <li class="<?= $active('/logs') ?>">
                    <a href="<?= Url::to(['/logs/index']) ?>">
                        <i class="fa fa-clipboard-list fa-lg text-warning"></i>
                        <strong class="special"><?= Yii::t('app', 'Logs') ?></strong>
                    </a>
                </li>
                <?php endif ?>

                <li class="<?= $active('/users') ?>">
                    <a href="<?= Url::to(['users/view', 'id' => app\components\WebApp::encrypt(Yii::$app->user->identity->id)]); ?>">
                        <i class="fas fa-user-circle fa-lg text-primary"></i>
                        <strong class="special"><?= Yii::t('app', 'Profile') ?></strong>
                    </a>
                </li>
                <li>
                    <?php
                    echo Html::beginForm(['/site/logout'], 'post')
                        . Html::submitButton(
                            '<i class="fa fa-sign-out-alt fa-lg mr-4"></i>' . Yii::t('app', 'Logout') . ' (' . Yii::$app->user->identity->first_name . ')',
                            ['class' => 'btn btn-link logout']
                        )
                        . Html::endForm();
                    ?>
                </li>
            </ul>
        </div>
    </nav>
</div>
