<?php

use app\widgets\WalletForm;
use yii\helpers\Html;
use yii\web\View;

/* @var $this yii\web\View */
/* @var $formModel app\models\WizardWalletForm */

$this->title = Yii::t('app', 'New wallet');

$form = WalletForm::begin(['id' => 'wizard-form']);
echo Html::activeHiddenInput($formModel, 'seed');
echo Html::activeHiddenInput($formModel, 'address');
WalletForm::end();

include('_js.php');

$steps = [1 => '_wizard-step1', 2 => '_wizard-step2', 3 => '_wizard-step3', 4 => '_wizard-step4'];
// passi mostrati uno alla volta; avanti/indietro con i pulsanti next-step / prev-step
$this->registerJs(<<<JS
(function () {
    var steps = document.querySelectorAll('[data-wizard-step]');
    var dots = document.querySelectorAll('[data-wizard-dot]');
    var current = 1;
    function show(step) {
        current = Math.min(Math.max(step, 1), steps.length);
        steps.forEach(function (el) { el.hidden = Number(el.dataset.wizardStep) !== current; });
        dots.forEach(function (el) {
            var n = Number(el.dataset.wizardDot);
            el.classList.toggle('active', n === current);
            el.classList.toggle('done', n < current);
        });
    }
    document.addEventListener('click', function (event) {
        if (event.target.closest('.next-step')) show(current + 1);
        if (event.target.closest('.prev-step')) show(current - 1);
    });
    show(1);
})();
JS, View::POS_END, 'wallet_wizard_steps');
?>
<div class="card card-md">
    <div class="card-body">
        <ol class="wallet-steps mb-4" aria-label="<?= Html::encode(Yii::t('app', 'Steps')) ?>">
            <?php foreach (array_keys($steps) as $n): ?>
                <li data-wizard-dot="<?= $n ?>"><span><?= $n ?></span></li>
            <?php endforeach ?>
        </ol>
        <?php foreach ($steps as $n => $view): ?>
            <section data-wizard-step="<?= $n ?>"<?= $n === 1 ? '' : ' hidden' ?>><?= $this->render($view) ?></section>
        <?php endforeach ?>
    </div>
</div>
