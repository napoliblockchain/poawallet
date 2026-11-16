<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

use app\models\search\TransactionsSearch;
use app\models\MPWallets;
use app\models\Users;
use app\models\Nodes;
use app\models\Logs;

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
	 * Mostra il seed solo con il PIN del wallet, verificato qui come per lo sblocco (mai decifrato nel browser).
	 * Senza PIN impostato il seed non si legge. Contatore proprio e severo: 3 errori bloccano per 24 ore.
	 */
	public function actionRevealSeed()
	{
		if (self::tooManyAttempts('seed')) {
			Yii::$app->response->statusCode = 429;
			return $this->json(['success' => false, 'locked' => true]);
		}
		$userId = (int) Yii::$app->user->id;
		$pin = (string) Yii::$app->request->post('pin', '');
		$storedPin = WebApp::decryptForUser(Yii::$app->request->post('crypted_pin'), $userId);
		if ($storedPin === null || preg_match('/^[0-9]{5}$/D', $pin) !== 1 || !hash_equals($storedPin['value'], $pin)) {
			self::registerFailure('seed');
			$locked = self::tooManyAttempts('seed');
			if ($locked) Logs::write('Master seed locked after wrong PINs: ' . Yii::$app->user->identity->username);
			return $this->json(['success' => false, 'locked' => $locked]);
		}
		$seed = WebApp::decryptForUser(Yii::$app->request->post('cryptedseed'), $userId);
		if ($seed === null) {
			return $this->json(['success' => false, 'locked' => false]);
		}
		Yii::$app->cache->delete(self::attemptsKey('seed'));
		Logs::write('Master seed revealed: ' . Yii::$app->user->identity->username);

		return $this->json([
			'success' => true,
			'seed' => $seed['value'],
			'upgraded' => [
				'cryptedseed' => $seed['legacy'] ? WebApp::encryptForUser($seed['value'], $userId) : null,
				'crypted_pin' => $storedPin['legacy'] ? WebApp::encryptForUser($pin, $userId) : null,
			],
		]);
	}

	/** tentativi e durata del blocco (secondi) per tipo di segreto: il seed dà il controllo totale dei fondi */
	private const SECRET_ATTEMPTS = ['pin' => [5, 900], 'seed' => [3, 86400]];

	private static function attemptsKey(string $kind): string
	{
		return 'wallet-secret-attempts:' . $kind . ':' . Yii::$app->user->id;
	}

	private static function tooManyAttempts(string $kind): bool
	{
		return (int) Yii::$app->cache->get(self::attemptsKey($kind)) >= self::SECRET_ATTEMPTS[$kind][0];
	}

	private static function registerFailure(string $kind): void
	{
		$key = self::attemptsKey($kind);
		Yii::$app->cache->set($key, (int) Yii::$app->cache->get($key) + 1, self::SECRET_ATTEMPTS[$kind][1]);
	}

}
