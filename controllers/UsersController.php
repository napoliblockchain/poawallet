<?php

namespace app\controllers;

use Yii;
use app\models\Users;
use app\models\MPWallets;
use app\models\Transactions;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

use app\models\PushSubscriptions;

use app\components\WebApp;
use app\components\Settings;
use yii\filters\AccessControl;

/**
 * UsersController implements the CRUD actions for Users model.
 */
class UsersController extends Controller
{
    public function beforeAction($action)
    {
        if ($action->id === 'change-password') {
            // Before CSRF/access filters: even rejected requests must never dump entered credentials.
            Yii::$app->response->headers->set('Cache-Control', 'no-store');
            foreach (Yii::$app->log->targets as $target) $target->logVars = [];
            Yii::getLogger()->traceLevel = 0;
            if (!Yii::$app->has('mutex')) Yii::$app->set('mutex', [
                'class' => \yii\mutex\FileMutex::class, 'mutexPath' => '@runtime/password-change-locks', 'dirMode' => 0700, 'fileMode' => 0600,
            ]);
        }
        return parent::beforeAction($action);
    }

    public function actionChangePassword()
    {
        $model = new \app\models\ChangePasswordForm();
        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            if ($model->change()) {
                Yii::$app->user->logout();
                Yii::$app->session->setFlash('success', Yii::t('app', 'Password changed. Please sign in again.'));
                return $this->redirect(['/site/login']);
            }
            if ($model->retryAfter > 0) {
                Yii::$app->response->statusCode = 429;
                Yii::$app->response->headers->set('Retry-After', (string) $model->retryAfter);
            }
        }
        $model->current_password = $model->password = $model->repeat_password = '';
        return $this->render('change-password', ['model' => $model]);
    }
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
                    'change-password' => ['GET', 'POST'],
                ],
            ],
        ];
    }

    private static function json ($data)
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}

    /**
     * Displays a single BoltSocialusers model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $wallet = MPWallets::find()
 	     		->andWhere(['id_user'=>Yii::$app->user->id])
 	    		->one();

        $node = Settings::poa(Yii::$app->user->id);

        $tokens_from = Transactions::find()
            ->andWhere(['from_address'=>$wallet->wallet_address])
            ->andWhere(['status'=>'complete']);

        $tokens_to = Transactions::find()
            ->andWhere(['to_address'=>$wallet->wallet_address])
            ->andWhere(['status'=>'complete']);

        // nessuna transazione: SUM restituisce null
        $sent['sum'] = round((float) $tokens_from->sum('token_price'), (int) $node->smartContract->decimals);
        $sent['count'] = (int) $tokens_from->count();

        $received['sum'] = round((float) $tokens_to->sum('token_price'), (int) $node->smartContract->decimals);
        $received['count'] = (int) $tokens_to->count();

        $total_transactions = $sent['count'] + $received['count'];

        // genero array per il grafico
        $chart = Transactions::find()
            ->orwhere(['=','from_address', $wallet->wallet_address])
            ->orwhere(['=','to_address', $wallet->wallet_address])
            ->andWhere(['status'=>'complete'])
            ->select('token_price, from_address, to_address')
            ->all();

        $userAccountValues = [0.01];
        $increase = 0;
        $color = 'gray';
        $arrow = 'arrows-alt-v';
        if (count ($chart) > 0){
            $actualPrice = 0;
            $symbol = '';
            foreach ($chart as $item){
                if ($item->from_address == $wallet->wallet_address){
                    $actualPrice -= $item['token_price'];
                } else {
                    $actualPrice += $item['token_price'];
                }

                $userAccountValues[] = $actualPrice;

            }

            $last1 = $userAccountValues[count($userAccountValues)-1];
            $last2 = $userAccountValues[count($userAccountValues)-2];

            if ($last1 == 0 || $last2 == 0) {
                // saldo a zero: variazione percentuale non definita
                $increase = 0;
            } elseif ($last1 > $last2){
                $increase = round($last1 / $last2 *100, 2);
                $color = 'green';
                $arrow = 'up';
            } else {
                $increase = round($last2 / $last1 *100, 2);
                $color = 'red';
                $arrow = 'down';
            }
        }

        $userAccountValueArray = [
            'accountValues' => $userAccountValues,
            'balance' => $received['sum'] - $sent['sum'],
            'increase' => $increase,
            'color' => $color,
            'arrow' => $arrow,
        ];

        return $this->render('view', [
            'model' => $this->findModel(WebApp::decrypt($id)),
            'sent' => $sent,
            'received' => $received,
            'transactions' => $total_transactions,
            'percent_sent' => $sent['count'] / (1+$total_transactions) * 100,
            'percent_received' => $received['count'] / (1+$total_transactions) * 100,
            'userAccountValueArray' => $userAccountValueArray,
            'node' => $node,
        ]);

    }

    /**
	 * Saves the Subscription for push messages.
	 * @param POST VAPID KEYS
	 * this function NOT REQUIRE user to login
	 */
	public function actionSaveSubscription()
	{
 		$raw = json_decode($_POST['subscription']);
		$browser = $_SERVER['HTTP_USER_AGENT'];

		$vapid = PushSubscriptions::find()
 	     		->andWhere(['id_user'=>Yii::$app->user->identity->id])
				->andWhere(['browser'=>$browser])
				->andWhere(['type'=>'wallet'])
 	    		->one();

		if (null === $vapid){
			//save
			$vapid = new PushSubscriptions;
			$vapid->id_user = Yii::$app->user->identity->id;
			$vapid->browser = $browser;
			$vapid->endpoint = $raw->endpoint;
			$vapid->auth = $raw->keys->auth;
			$vapid->p256dh = $raw->keys->p256dh;
			$vapid->type = 'wallet';

			if (!$vapid->save()){
				$data = ['response' => '[WalletController] SaveSubscription: Cannot save subscription on server!'];
			} else {
				$data = ['response' => '[WalletController] SaveSubscription: Subscription saved on server.'];
			}
		}else{
			if (!$vapid->delete()){
				$data = ['response' => '[WalletController] SaveSubscription: Cannot delete subscription on server!'];
			} else {
				$data = ['response' => '[WalletController] SaveSubscription: Subscription deleted on server.'];
			}
		}
		return $this->json($data);
	}

    /**
     * Updates an existing BoltSocialusers model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel(WebApp::decrypt($id));

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_social]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Finds the BoltSocialusers model based on its user_id value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Users the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        // each user can only see and change their own profile
        if ((int) $id !== (int) Yii::$app->user->id) {
            throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
        }
        $model = Users::find()->andWhere(['id'=>$id])->one();
        if ( $model !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
}
