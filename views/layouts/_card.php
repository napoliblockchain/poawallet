<?php

/* Scheda standard delle pagine di impostazione: titolo, azioni a destra, contenuto (HTML già sicuro) e piede facoltativo. */
/* @var $title string */
/* @var $body string */
/* @var $actions string|null */
/* @var $footer string|null */
/* @var $flush bool contenuto a filo (tabelle, elenchi) invece che con margini */

use yii\helpers\Html;
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= Html::encode($title) ?></h2>
        <?php if (!empty($actions)): ?><div class="card-actions d-flex gap-2"><?= $actions ?></div><?php endif ?>
    </div>
    <?= empty($flush) ? '<div class="card-body">' . $body . '</div>' : $body ?>
    <?php if (!empty($footer)): ?><div class="card-footer"><?= $footer ?></div><?php endif ?>
</div>
