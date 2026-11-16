<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;

use app\models\WizardWalletForm;
use app\models\MPWallets;
use app\models\Nodes;

use app\components\Networks;

class WizardController extends Controller
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
				// all actions require login (fail secure): no 'only' list to keep in sync
				'rules' => [
					[
						'allow' => true,
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
	 * Show wizard generation first wallet address page
	 */
	public function actionIndex()
 	{
		$this->layout = 'wizard';
		$formModel = new WizardWalletForm; //form di input dei dati

		$defaultNetworkExist = false;
		if ($formModel->load(Yii::$app->request->post()) && $formModel->validate()) {
			// se sono giunto qui, l'indirizzo dell'utente non doveva essere in tabella
			// oppure non corrisponde a quello salvato in indexedDB
			$boltWallet = MPWallets::find()->where( [ 'id_user' => Yii::$app->user->id ] )->one();
			$node = Nodes::find()->byUserId(Yii::$app->user->id)->one();

			if(null === $boltWallet) {
				$boltWallet = new MPWallets;
				$boltWallet->id_user = Yii::$app->user->id;
				if (null === $node){
					$defaultNetworkExist = true;
					$node = Networks::createDefaults();
				}
				$boltWallet->blocknumber = $this->getLatestBlockNumber();
			}
			$boltWallet->wallet_address = Yii::$app->request->post('WizardWalletForm')['address'];

			if ($boltWallet->save()){
				if (null === $node && $defaultNetworkExist === false){
					$node = Networks::createDefaults();
				}
				return $this->redirect(['/wallet/index']);
			} else {
				var_dump( $boltWallet->getErrors());
				exit;
			}
    	}

		return $this->render('index', [
			'formModel' => $formModel,
		]);
 	}

	private function getLatestBlockNumber()
	{
		$ERC20 = new Yii::$app->Erc20(Yii::$app->user->id);
		$block = $ERC20->getBlockInfo();

		$json = null;
		if (!is_object($block)) {
			$json = json_decode($block);
		}

		if (!isset($json->error)) {
			return ($block === null) ? '0x0' : $block->number;
		} else {
			return '0x0';
		}
	}

}
