<?php

use app\assets\ClipboardCopyAsset;
use Com\Tecnick\Barcode\Barcode;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $fromAddress string */

$this->title = Yii::t('app', 'Receive');
// QR in SVG (tc-lib-barcode, come il POS): niente immagini né GD; descrizione testuale tolta, l'indirizzo è già mostrato
$qrSvg = preg_replace('~<desc>.*?</desc>~s', '', (new Barcode())->getBarcodeObj('QRCODE,M', $fromAddress, -6, -6, '#0b151f', [-2, -2, -2, -2])->getInlineSvgCode());
ClipboardCopyAsset::register($this);
?>
<div class="card text-center">
    <div class="card-body">
        <h2 class="card-title mb-1"><?= Html::encode(Yii::t('app', 'Receive')) ?></h2>
        <p class="text-secondary mb-3"><?= Html::encode(Yii::t('app', 'Show this code to whoever has to pay you, or share the address.')) ?></p>
        <div class="wallet-qr shadow-sm mb-3 mx-auto" role="img" aria-label="<?= Html::encode(Yii::t('app', 'QR code of the wallet address')) ?>"><?= $qrSvg ?></div>
        <p class="wallet-address mb-3"><?= Html::encode($fromAddress) ?></p>
        <input type="hidden" readonly="readonly" id="inputcopyWalletAddress" value="<?= Html::encode($fromAddress) ?>">
        <button type="button" class="btn btn-primary copyonClickAddress"><i class="far fa-copy me-2" aria-hidden="true"></i><?= Html::encode(Yii::t('app', 'Copy address')) ?></button>
    </div>
</div>
