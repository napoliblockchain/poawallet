<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\HttpException;

use app\components\RealtimeTicket;

/**
 * Ticket del flusso in tempo reale (SIP-029 passo 2): wallet-sync.js lo chiede prima di aprire /events
 * e alla sua scadenza. POST con CSRF, solo utente collegato.
 */
class RealtimeController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['ticket' => ['POST']],
            ],
        ];
    }

    public function actionTicket()
    {
        if (!RealtimeTicket::issue((int) Yii::$app->user->id)) {
            // il browser ripiega sul controllo periodico delle notifiche
            throw new HttpException(503, Yii::t('app', 'Realtime updates are not available.'));
        }
        Yii::$app->response->statusCode = 204;
        return '';
    }
}
