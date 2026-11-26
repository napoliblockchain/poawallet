<?php

namespace app\commands;

use app\daemons\EthereumTransactions;
use yii\console\Controller;

class WebsocketController extends Controller
{
    public function actionTrades()
    {
        $txs = new EthereumTransactions();
        $txs->start();
    }
}
