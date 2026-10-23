<?php

namespace app\controllers;

use app\models\NotificationsReaders;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * Mailbox compatibility bridge (tokenpay routes -> poawallet notifications center).
 */
class MailboxController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete-all' => ['POST'],
                    'delete-inbox' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionInbox()
    {
        return $this->redirect(['messages/index']);
    }

    public function actionView($id = null, $tag = 'inbox')
    {
        if ($id !== null) {
            $model = NotificationsReaders::findOne([
                'id' => (int)$id,
                'id_user' => Yii::$app->user->id,
            ]);
            if ($model !== null && (int)$model->alreadyread !== NotificationsReaders::STATUS_READ) {
                $model->alreadyread = NotificationsReaders::STATUS_READ;
                $model->save(false, ['alreadyread']);
            }
        }

        return $this->redirect(['messages/index']);
    }

    public function actionDeleteAll($tag = 'inbox')
    {
        $ids = Yii::$app->request->post('id', []);
        if (!is_array($ids)) {
            return $this->redirect(['inbox']);
        }

        foreach ($ids as $id) {
            $model = NotificationsReaders::findOne([
                'id' => (int)$id,
                'id_user' => Yii::$app->user->id,
            ]);
            if ($model !== null) {
                $model->delete();
            }
        }

        return $this->redirect(['inbox']);
    }

    public function actionDeleteInbox($id)
    {
        $model = NotificationsReaders::findOne([
            'id' => (int)$id,
            'id_user' => Yii::$app->user->id,
        ]);
        if ($model !== null) {
            $model->delete();
        }

        return $this->redirect(['inbox']);
    }
}
