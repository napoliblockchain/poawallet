<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\web\HttpException;
use yii\filters\VerbFilter;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

use app\models\search\TransactionsSearch;
use app\models\MPWallets;
use app\models\Users;
use app\models\Nodes;
use app\models\Logs;

use yii\bootstrap5\ActiveForm;
use yii\helpers\Json;
use yii\helpers\Url;

use app\components\WebApp;

class WalletController extends Controller
{


	public function beforeAction($action)
	{
		Yii::$app->user->authTimeout = Yii::$app->params['user.rememberMeDuration'];
		// CSRF only on the secret endpoints (pages using them send the token through yii.js)
    	$this->enableCsrfValidation = in_array($action->id, ['verify-pin', 'reveal-seed'], true);
    	return parent::beforeAction($action);
	}


	/**
	 * {@inheritdoc}
	 */
	public function behaviors()
	{
		return [
			'access' => [
				'class' => AccessControl::className(),
				// all actions require login (fail secure): no 'only' list to keep in sync
				'rules' => [
					[
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => VerbFilter::class,
				'actions' => [
					'crypt' => ['POST'],
					'verify-pin' => ['POST'],
					'reveal-seed' => ['POST'],
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function actions()
	{
		return [
			'error' => [
				'class' => 'yii\web\ErrorAction',
				'layout' => 'auth',
			],
			'captcha' => [
				'class' => 'yii\captcha\CaptchaAction',
				'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
			],
		];
	}

	// public function actionError(){
    //     $this->layout = 'auth';
    //     return $this->render('error');
    // }


	private function loadSocialUser()
	{
		$user = Users::find()
 	     		->andWhere(['id'=>Yii::$app->user->id])
 	    		->one();

		return $user;
	}

	/**
	 * @param POST string address the Ethereum Address to be rescanned
	 */
	public function actionRescan(){
        //azzero il numero dei blocchi dell'indirizzo

		$model = MPWallets::find()->byUserId(Yii::$app->user->id)->one();
		$model->blocknumber = '0x0';
		//$model->save();

		return $this->json([
			'success' => $model->save(),
		]);
	}



	/**
	 * Lists wallet dashboard page
	 */
	public function actionIndex()
	{
		$fromAddress = MPWallets::find()->userAddress(Yii::$app->user->id);
		$node = Nodes::find()
 	     		->andWhere(['id_user'=>Yii::$app->user->id])
 	    		->one();

		if (NULL === $fromAddress || NULL === $node){
			$session = Yii::$app->session;
			$string = Yii::$app->security->generateRandomString(32);
			$session->set('token-wizard', $string );

			return $this->redirect(['wizard/index','token' => $string]);
		}


		$searchModel = new TransactionsSearch();
		$dataProvider = $searchModel->search(Yii::$app->request->queryParams);
		$dataProvider->setPagination(['pageSize' => 5]);
		$dataProvider->sort->defaultOrder = ['invoice_timestamp' => SORT_DESC];
		$dataProvider->query
					->orwhere(['=','to_address', $fromAddress])
					->orwhere(['=','from_address', $fromAddress]);

        // $dataProvider->query->andwhere(['=','id_smart_contract', $node->id_smart_contract]);

		// $ERC20 = new Yii::$app->Erc20(Yii::$app->user->id);
		//
		// $balance =
		// echo '<pre>'.print_r($ERC20->tokenBalance($fromAddress),true).'</pre>';
		// exit;



		return $this->render('index', [
				'searchModel' => $searchModel,
				'dataProvider' => $dataProvider,
				'fromAddress' => $fromAddress,
				'balance' => Yii::$app->Erc20->tokenBalance($fromAddress),
				'userImage' => $this->loadSocialUser()->picture,
				'balance_gas' => Yii::$app->Erc20->gasBalance($fromAddress),
				'node' => $node,
		]);
	}


	private static function json ($data)
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}

	/**
	 * cifra password, seed e PIN legandoli all'utente loggato (vedi WebApp::encryptForUser)
	 */
	public function actionCrypt()
	{
		$userId = (int) Yii::$app->user->id;
		$data = [
			'cryptedpass' => isset($_POST['pass']) ? WebApp::encryptForUser($_POST['pass'], $userId) : '',
			'cryptedseed' => isset($_POST['seed']) ? WebApp::encryptForUser($_POST['seed'], $userId) : '',
			'cryptediduser' => WebApp::encrypt($userId),
		];

		return $this->json($data);
	}

	/**
	 * verifica il PIN digitato contro quello cifrato salvato nel browser: il PIN in chiaro non torna al client
	 */
	public function actionVerifyPin()
	{
		if (self::tooManyAttempts('pin')) {
			Yii::$app->response->statusCode = 429;
			return $this->json(['valid' => false, 'locked' => true]);
		}
		$userId = (int) Yii::$app->user->id;
		$stored = WebApp::decryptForUser(Yii::$app->request->post('crypted_pin'), $userId);
		$pin = (string) Yii::$app->request->post('pin', '');
		if ($stored === null || $pin === '' || !hash_equals($stored['value'], $pin)) {
			self::registerFailure('pin');
			return $this->json(['valid' => false]);
		}
		Yii::$app->cache->delete(self::attemptsKey('pin'));

		return $this->json([
			'valid' => true,
			// PIN salvato nel formato precedente: il browser lo sostituisce con quello legato all'utente
			'upgraded' => $stored['legacy'] ? WebApp::encryptForUser($pin, $userId) : null,
		]);
	}

	/**
	 * mostra il seed solo dopo aver verificato la password del wallet
	 */
	public function actionRevealSeed()
	{
		if (self::tooManyAttempts('seed')) {
			Yii::$app->response->statusCode = 429;
			return $this->json(['success' => false, 'locked' => true]);
		}
		$userId = (int) Yii::$app->user->id;
		$password = (string) Yii::$app->request->post('password', '');
		$storedPassword = WebApp::decryptForUser(Yii::$app->request->post('prv_pas'), $userId);
		if ($storedPassword === null || $password === '' || !hash_equals($storedPassword['value'], $password)) {
			self::registerFailure('seed');
			return $this->json(['success' => false]);
		}
		$seed = WebApp::decryptForUser(Yii::$app->request->post('cryptedseed'), $userId);
		if ($seed === null) {
			return $this->json(['success' => false]);
		}
		Yii::$app->cache->delete(self::attemptsKey('seed'));
		Logs::write('Master seed revealed: ' . Yii::$app->user->identity->username);

		return $this->json([
			'success' => true,
			'seed' => $seed['value'],
			'upgraded' => [
				'cryptedseed' => $seed['legacy'] ? WebApp::encryptForUser($seed['value'], $userId) : null,
				'prv_pas' => $storedPassword['legacy'] ? WebApp::encryptForUser($storedPassword['value'], $userId) : null,
			],
		]);
	}

	private const MAX_SECRET_ATTEMPTS = 5;
	private const SECRET_ATTEMPTS_WINDOW = 900; // 15 minuti

	private static function attemptsKey(string $kind): string
	{
		return 'wallet-secret-attempts:' . $kind . ':' . Yii::$app->user->id;
	}

	private static function tooManyAttempts(string $kind): bool
	{
		return (int) Yii::$app->cache->get(self::attemptsKey($kind)) >= self::MAX_SECRET_ATTEMPTS;
	}

	private static function registerFailure(string $kind): void
	{
		$key = self::attemptsKey($kind);
		Yii::$app->cache->set($key, (int) Yii::$app->cache->get($key) + 1, self::SECRET_ATTEMPTS_WINDOW);
	}






}
