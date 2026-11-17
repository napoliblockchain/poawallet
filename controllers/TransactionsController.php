<?php

namespace app\controllers;

use Yii;
use app\models\Transactions;
use app\models\search\TransactionsSearch;
use app\models\MPWallets;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;

use app\components\WebApp;
use app\components\ChainConfig;
use app\components\Erc20;
use yii\filters\AccessControl;

/**
 * TransactionsController implements the CRUD actions for Transactions model.
 */
class TransactionsController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                // all actions require login (fail secure)
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Transactions models.
     * @return mixed
     */
    public function actionIndex()
    {
        $fromAddress = MPWallets::find()->userAddress(Yii::$app->user->id);

		if (NULL === $fromAddress){
			$session = Yii::$app->session;
			$string = Yii::$app->security->generateRandomString(32);
			$session->set('token-wizard', $string );
			return $this->redirect(['wizard/index','token' => $string]);
		}

        $searchModel = new TransactionsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setPagination(['pageSize' => 10]);
		$dataProvider->sort->defaultOrder = ['invoice_timestamp' => SORT_DESC];
		$dataProvider->query
					->orwhere(['=','to_address', $fromAddress])
					->orwhere(['=','from_address', $fromAddress]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'fromAddress' => $fromAddress,
        ]);
    }

    /**
     * Riga aggiornata di una transazione e saldo, per il flusso in tempo reale (wallet-sync.js, evento "tx").
     * Solo transazioni dell'utente (findModel); saldo null se la chain non risponde.
     */
    public function actionRow($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel((int) $id);
        $fromAddress = MPWallets::find()->userAddress(Yii::$app->user->id);
        $balance = null;
        try {
            $balance = WebApp::tokenAmount(Yii::$app->Erc20->tokenBalance($fromAddress), ChainConfig::tokenDecimals());
        } catch (\Throwable $error) {
            Yii::warning('Token balance not available.', __METHOD__);
        }
        return ['id' => (int) $model->id, 'row' => WebApp::showTransactionRow($model, $fromAddress, true), 'balance' => $balance];
    }

    /**
     * Displays a single Transactions model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel(WebApp::decrypt($id)),
        ]);
    }

    /**
     * Displays a single Transactions model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionGetTransactionDetails($txhash)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!is_string($txhash) || preg_match('/^0x[0-9a-fA-F]{64}$/D', $txhash) !== 1) {
            throw new BadRequestHttpException(Yii::t('app', 'Invalid request.'));
        }
        // solo una transazione dell'utente (autore, mittente o destinatario): mai aggiornare quella di un altro
        $transaction = null;
        foreach (Transactions::find()->where(['txhash' => $txhash])->all() as $candidate) {
            if ($candidate->belongsToUser((int) Yii::$app->user->id)) { $transaction = $candidate; break; }
        }
        if ($transaction === null) throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));

        $receipt = (new Erc20(Yii::$app->user->id))->getReceipt($txhash);
        if (!is_object($receipt)) return ['success' => false, 'receipt' => null];
        // importo solo dal Transfer del token TTS e solo se la transazione è riuscita
        $amount = ($receipt->status ?? null) === '0x1'
            ? Erc20::transferAmount($receipt, ChainConfig::get()->smartContract->smart_contract_address, ChainConfig::tokenDecimals())
            : null;
        if ($amount !== null && ($transaction->status !== 'complete' || (float) $transaction->token_received !== $amount)) {
            $transaction->blocknumber = (string) $receipt->blockNumber;
            $transaction->token_received = $amount;
            $transaction->status = 'complete';
            if (!$transaction->save()) Yii::error('Transaction receipt update not saved.', __METHOD__);
        }
        return ['success' => $amount !== null, 'receipt' => $receipt];
    }

    /**
     * Finds the Transactions model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Transactions the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $model = Transactions::findOne($id);
        if ($model !== null && $model->belongsToUser((int) Yii::$app->user->id)) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }

}
