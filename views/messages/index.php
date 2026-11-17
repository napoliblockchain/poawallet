<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel app\models\BoltTokensSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Messages');
$deleteUrl = yii\helpers\Url::to(['/messages/delete']);
$deleteMessage = Yii::t('app','Are you sure you want to delete selected items?');

$deleting = <<<JS

    $(function(){
        // intercetta il pulsante Remove PIN e mostra la schermata di inserimento pin
        if ($('.btn-delete').length){
            var deleteNotificationButton = document.querySelector('.btn-delete');
            deleteNotificationButton.addEventListener('click', function(){
                if (confirm('{$deleteMessage}')) {
                    var keys = $('#notifications-form').yiiGridView('getSelectedRows');
                    console.log('[delete] valori selezionati:',keys);
                    $.ajax({
                        url: '{$deleteUrl}',
                        data: {
                            keys: JSON.stringify(keys),
                        },
                        type: "POST",
                        success: function(result) {
                            // reload page from redirect
                        }
                    });
                }
            });
        }
    });
JS;

$this->registerJs(
    $deleting,
    yii\web\View::POS_READY, //POS_END
    'deleting'
);
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= Html::encode(Yii::t('app', 'Notifications list')) ?></h2>
        <?php if ($dataProvider->totalCount > 0): ?>
            <div class="card-actions"><?= Html::button('<i class="far fa-trash-alt me-1" aria-hidden="true"></i>' . Html::encode(Yii::t('app', 'Delete')), ['class' => 'btn btn-sm btn-outline-danger btn-delete']) ?></div>
        <?php endif ?>
    </div>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'id' => 'notifications-form',
        'showHeader' => false,
        'layout' => "{items}\n<div class=\"card-footer d-flex justify-content-center\">{pager}</div>",
        'emptyText' => Yii::t('app', 'No notifications.'),
        'emptyTextOptions' => ['class' => 'text-secondary text-center py-4'],
        'tableOptions' => ['class' => 'table-96 table wallet-tx-table table-vcenter mb-0'],
        'columns' => [
            ['class' => 'yii\grid\CheckboxColumn', 'name' => 'id', 'contentOptions' => ['class' => 'ps-3 w-1'], 'checkboxOptions' => ['class' => 'form-check-input']],
            ['format' => 'raw', 'value' => static fn($data) => app\components\WebApp::showMessagesRow($data)],
        ],
    ]) ?>
</div>
