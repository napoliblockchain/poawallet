<?php
namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

use app\models\WizardWalletForm;
use app\models\MPWallets;

use yii\bootstrap5\ActiveForm;

class RestoreController extends Controller
{
	public function beforeAction($action)
	{
    	$this->enableCsrfValidation = false;
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
				'only' => [
					'index',
				],
				'rules' => [

					[
						'allow' => true,
						'actions' => [
							'index',
						],
						'roles' => ['@'],
					],
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
			],
			'captcha' => [
				'class' => 'yii\captcha\CaptchaAction',
				'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
			],
		];
	}

	/**
	 * Show Restore old wallet page
	 */
	public function actionIndex()
 	{
		$this->layout = 'wizard';
		$formModel = new WizardWalletForm; //form di input dei dati

		if (Yii::$app->request->isAjax && $formModel->load(Yii::$app->request->post())) {
		    Yii::$app->response->format = Response::FORMAT_JSON;
		    return ActiveForm::validate($formModel);
		}

		if ($formModel->load(Yii::$app->request->post()) && $formModel->validate()) {
			// se sono giunto qui, l'indirizzo dell'utente non doveva essere in tabella
			// oppure non corrisponde a quello salvato in indexedDB
			// rete e token dalle variabili d'ambiente (SIP-032): nessuna riga di rete per utente
			$boltWallet = MPWallets::find()->where( [ 'id_user' => Yii::$app->user->id ] )->one();
			if(null === $boltWallet) {
				$boltWallet = new MPWallets;
				$boltWallet->id_user = Yii::$app->user->id;
				$boltWallet->blocknumber = $this->getLatestBlockNumber();
			}
			$boltWallet->wallet_address = Yii::$app->request->post('WizardWalletForm')['address'];

			if ($boltWallet->save()){
				return $this->redirect(['/wallet/index']);
			}
			Yii::error('Restored wallet address not saved.', __METHOD__);
			$formModel->addError('seed', Yii::t('app', 'Wallet not saved: please retry.'));
    	}

 		return $this->render('index', [
			'formModel' => $formModel,
		]);
 	}

	private function getLatestBlockNumber(){
		$ERC20 = new Yii::$app->Erc20(Yii::$app->user->id);
		$block = $ERC20->getBlockInfo();

		$json = null;
		if (!is_object($block)){
			$json = json_decode($block);
		}

		if (!isset($json->error)){
			return ($block === null) ? '0x0' : $block->number;
		} else {
			return '0x0';
		}
	}

}
