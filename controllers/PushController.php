<?php

namespace app\controllers;

use app\models\NotificationsReaders;
use app\models\PushSubscriptions;
use Yii;
use yii\helpers\Json;
use yii\web\Controller;

/**
 * Push endpoints for subscription lifecycle and notification ack.
 */
class PushController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::className(),
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => ['save' => ['POST'], 'notified' => ['POST']],
            ],
        ];
    }

    public function actionNotified()
    {
        try {
            $payload = Json::decode(Yii::$app->request->getRawBody());
        } catch (\yii\base\InvalidArgumentException $e) {
            throw new \yii\web\BadRequestHttpException('Invalid JSON');
        }
        $id = is_array($payload) ? filter_var($payload['model_id'] ?? null,
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) {
            throw new \yii\web\BadRequestHttpException('Invalid notification id');
        }
        $data = ['success' => false];

        if (!Yii::$app->user->isGuest) {
            $model = NotificationsReaders::findOne([
                'id' => $id,
                'id_user' => Yii::$app->user->id,
            ]);

            if ($model !== null) {
                $model->alreadyread = NotificationsReaders::STATUS_READ;
                if ($model->save(false, ['alreadyread'])) {
                    $data = ['success' => true];
                }
            }
        }

        return Json::encode($data);
    }

    /**
     * Saves/removes browser push subscription.
     */
    public function actionSave()
    {
        if (Yii::$app->user->isGuest) {
            return Json::encode(['response' => '[PushController] User not authenticated']);
        }

        $raw = null;
        if (isset($_POST['subscription'])) {
            $raw = json_decode($_POST['subscription']);
        }

        $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown-browser';
        $type = 'wallet';

        $model = PushSubscriptions::find()
            ->andWhere(['id_user' => Yii::$app->user->id])
            ->andWhere(['browser' => $browser])
            ->andWhere(['type' => $type])
            ->one();

        if ($raw !== null) {
            if ($model === null) {
                $model = new PushSubscriptions();
                $model->id_user = Yii::$app->user->id;
                $model->browser = $browser;
                $model->type = $type;
            }

            $model->endpoint = $raw->endpoint ?? '';
            $model->auth = $raw->keys->auth ?? '';
            $model->p256dh = $raw->keys->p256dh ?? '';

            if (!$model->save()) {
                return Json::encode([
                    'response' => '[PushController] Cannot save subscription',
                    'errors' => $model->getErrors(),
                ]);
            }

            return Json::encode(['response' => '[PushController] Subscription saved']);
        }

        if ($model !== null) {
            if (!$model->delete()) {
                return Json::encode(['response' => '[PushController] Cannot delete subscription']);
            }
            return Json::encode(['response' => '[PushController] Subscription deleted']);
        }

        return Json::encode(['response' => '[PushController] Nothing to delete']);
    }
}
