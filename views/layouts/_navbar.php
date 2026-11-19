<?php

use app\components\UserRole;
use app\components\WebApp;
use yii\helpers\Html;
use yii\helpers\Url;

/* Menu principale (in basso su mobile, in linea su desktop) e pannello "Altro" con le altre voci. */
$route = Yii::$app->requestedRoute ?? '';
$isActive = static fn(string $prefix): bool => str_starts_with($route, $prefix);
$main = [
    ['wallet', ['/wallet/index'], 'fas fa-wallet', Yii::t('app', 'My Wallet')],
    ['send', ['/send/index'], 'far fa-paper-plane', Yii::t('app', 'Send')],
    ['receive', ['/receive/index'], 'fas fa-qrcode', Yii::t('app', 'Receive')],
    ['transactions', ['/transactions/index'], 'fas fa-list', Yii::t('app', 'Transactions')],
];
$more = [
    ['messages', ['/messages/index'], 'fas fa-envelope', Yii::t('app', 'Notifications')],
    // rete e token dalle variabili d'ambiente (SIP-032): solo la pagina informativa
    ['network', ['/network/index'], 'fas fa-network-wired', Yii::t('app', 'Network info')],
];
if (UserRole::isWebmaster()) {
    $more[] = ['profiles', ['/profiles/index'], 'fas fa-id-badge', Yii::t('app', 'Profiles')];
    $more[] = ['logs', ['/logs/index'], 'fas fa-clipboard-list', Yii::t('app', 'Logs')];
}
$more[] = ['users', ['/users/view', 'id' => WebApp::encrypt(Yii::$app->user->id)], 'fas fa-user-circle', Yii::t('app', 'Profile')];
$more[] = ['users', ['/users/change-password'], 'fas fa-key', Yii::t('app', 'Change password')];
$moreActive = (bool) array_filter($more, static fn(array $item) => $isActive($item[0]));
?>
<nav class="wallet-tabbar d-print-none" aria-label="<?= Html::encode(Yii::t('app', 'Main menu')) ?>">
    <div class="container-xl">
        <ul class="nav">
            <?php foreach ($main as [$prefix, $url, $icon, $label]): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $isActive($prefix) ? ' active' : '' ?>" href="<?= Url::to($url) ?>"<?= $isActive($prefix) ? ' aria-current="page"' : '' ?>>
                        <i class="<?= $icon ?>" aria-hidden="true"></i><span><?= Html::encode($label) ?></span>
                    </a>
                </li>
            <?php endforeach ?>
            <li class="nav-item">
                <a class="nav-link<?= $moreActive ? ' active' : '' ?>" href="#wallet-more" data-bs-toggle="offcanvas" role="button" aria-controls="wallet-more">
                    <i class="fas fa-ellipsis-h" aria-hidden="true"></i><span><?= Html::encode(Yii::t('app', 'More')) ?></span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="offcanvas offcanvas-end" tabindex="-1" id="wallet-more" aria-labelledby="wallet-more-title">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h3" id="wallet-more-title"><?= Html::encode(Yii::$app->user->identity->email ?? Yii::t('app', 'Menu')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= Html::encode(Yii::t('app', 'Close')) ?>"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
            <?php foreach ($more as [$prefix, $url, $icon, $label]): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-3<?= $isActive($prefix) ? ' active' : '' ?>" href="<?= Url::to($url) ?>">
                    <i class="<?= $icon ?> fa-fw" aria-hidden="true"></i><?= Html::encode($label) ?>
                </a>
            <?php endforeach ?>
        </div>
        <div class="p-3">
            <?= Html::beginForm(['/site/logout'], 'post')
                . Html::submitButton('<i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Logout')), ['class' => 'btn btn-outline-danger w-100'])
                . Html::endForm() ?>
        </div>
    </div>
</div>
