<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\web\HttpException;

use app\models\Transactions;
use app\components\WalletKeyCipher;
use app\models\MPWallets;
use app\models\SendForm;
use app\models\Nodes;

use yii\bootstrap5\ActiveForm;
use yii\helpers\Json;
use yii\helpers\Url;

use app\components\WebApp;

use app\components\Settings;
use app\components\Erc20;
use app\components\NotificationService;

class SendController extends Controller
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
	 * List send page
	 */
	public function actionIndex()
 	{
 		$fromAddress = MPWallets::find()->userAddress(Yii::$app->user->id);
		if (null === $fromAddress){
			$session = Yii::$app->session;
			$string = Yii::$app->security->generateRandomString(32);
			$session->set('token-wizard', $string );
			return $this->redirect(['wizard/index','token' => $string]);
		}

		$formModel = new SendForm; //form di input dei dati

		if (Yii::$app->request->isAjax && $formModel->load(Yii::$app->request->post())) {
		    Yii::$app->response->format = Response::FORMAT_JSON;
		    return ActiveForm::validate($formModel);
		}

		if ($formModel->load(Yii::$app->request->post()) && $formModel->validate()) {
        	return $this->redirect(['/wallet/index']);
    	}

		$node = Nodes::find()
 	     		->andWhere(['id_user'=>Yii::$app->user->id])
 	    		->one();

 		return $this->render('index', [
 			'fromAddress' => $fromAddress,
			'sendForm' => $formModel,
			'balance' => Yii::$app->Erc20->tokenBalance($fromAddress),
			'balance_gas' => Yii::$app->Erc20->gasBalance($fromAddress),
			'node' => $node,
 		]);
 	}
	public function actionGasLimit()
	{
		$fromAddress = $_POST['from'];
		$toAddress = $_POST['to'];
		$amount = $_POST['amount'];

		$return = ['success'=>false,'gasLimit'=>0];

		$formModel = new SendForm; //form di input dei dati
		if (!$formModel->validate(Yii::$app->request->post())) {
		    return $response;
		}

		$ERC20 = new Yii::$app->Erc20(Yii::$app->user->id);
		$gasLimit = $ERC20->getGasLimit($toAddress,$fromAddress,$amount);

		if (null !== $gasLimit){
			$return = ['success'=>true,'gasLimit'=>$gasLimit];
		}
		return Json::encode($return);
	}

	/**
	 * List send page
	 */
	public function actionGenerateTransaction()
 	{
		$WebApp = new WebApp;

		$fromAccount = $_POST['from'];
 		$toAccount = $_POST['to'];
 		$amount = $_POST['amount'];
		$memo = $_POST['memo'];
		$encrypted = $_POST['prv_key'];
		// password del wallet: decifrata solo se appartiene all'utente loggato
		$storedPassword = WebApp::decryptForUser($_POST['prv_pas'] ?? null, (int) Yii::$app->user->id);
		if (null === $storedPassword){
			throw new HttpException(403,'Cannot decrypt private key.');
		}
		$passphrase = $storedPassword['value'];
		$decrypted = is_string($encrypted) ? WalletKeyCipher::decrypt($encrypted, $passphrase) : null;
		if (null === $decrypted){
			throw new HttpException(404,'Cannot decrypt private key.');
		}

		$id_user = Yii::$app->user->id;

		$settings = Settings::poa($id_user);
		$ERC20 = new Yii::$app->Erc20($id_user);

		// carico il gas in caso questo sia a 0 MA SOLO
		// se mi trovo sul network POA 2 e 3 inserito di default
		// nel DB
		// zerogas => 1

		if ($settings->blockchain->zerogas == 1){
			$gasBalance = $ERC20->loadGas($fromAccount);
		} else {
			$gasBalance = $ERC20->gasBalance($fromAccount);
		}

		// carico le informazioni relative al blocco attuale
		$block = $ERC20->getBlockInfo();

		// imposto il valore del nonce attuale
		$nonce = $ERC20->getNonce($fromAccount);

		// genero la transazione nell'intervallo del nonce
		$maxNonce = $nonce + 10;
		while ($nonce < $maxNonce)
		{
			$tx = $ERC20->SendToken([
				'nonce' => $nonce,
				'from' => $fromAccount, //indirizzo commerciante
				'contractAddress' => $settings->smartContract->smart_contract_address, //indirizzo contratto
				'toAccount' => $toAccount,
				'amount' => $amount, // $amountForContract,
				'gas' => '0x200b20', // $gas se supera l'importo 0x200b20 va in eerrore gas exceed limit !!!!!!
				'gasPrice' => '1000', // gasPrice giusto?
				'value' => '0',
				'chainId' => $settings->blockchain->chain_id,
				'decryptedSign' => $decrypted,
				'reference' => Erc20::napayReference($memo),
			]);

			if ($tx['success'] == true){
				break;
			} else {
				$nonce++;
			}
		}
		if ($tx['success'] == false){
			throw new HttpException(505,'Error: '.$tx['message']);
		}

		//salva la transazione ERC20 in archivio
		$timestamp = time();
		$invoice_timestamp = $timestamp;

		//calcolo expiration time
		$totalseconds = 15 * 60; //poa_expiration è in minuti, * 60 lo trasforma in secondi
		$expiration_timestamp = $timestamp + $totalseconds; //DEFAULT = 15 MINUTES

		$tokens = new Transactions;
		$tokens->id_user = $id_user;
		$tokens->status = 'new';
		$tokens->type = 'token';
		$tokens->id_smart_contract = $settings->smartContract->id;
		$tokens->token_price = $amount;
		$tokens->token_received = 0;
		$tokens->invoice_timestamp = $invoice_timestamp;
		$tokens->expiration_timestamp = $expiration_timestamp;
		$tokens->from_address = $fromAccount;
		$tokens->to_address = $toAccount;
		$tokens->blocknumber = $block->number;
		$tokens->txhash = $tx['tx'];
		$tokens->message = $memo;

		if (!($tokens->save())){
			throw new HttpException(404,print_r($tokens->errors));
		}

		// notifica per chi ha inviato (from_address)
		$notification = [
			'type' => 'token',
			'id_user' => $id_user,
			'status' => 'new',
			'description' => Yii::t('app','You sent a new transaction.'),
			'url' => Url::to(["/transactions/view",'id'=>WebApp::encrypt($tokens->id)]),
			'timestamp' => time(),
			'price' => $tokens->token_price,
		];
		NotificationService::notify($notification);

		//adesso posso uscire CON IL JSON DA REGISTRARE NEL SW.
		$data = [
			'id' => $WebApp->encrypt($tokens->id), //NECESSARIO PER IL SALVATAGGIO IN  indexedDB quando ritorna al Service Worker
			'status' => $tokens->status,
			'url' => Url::to(['/send/validate-transaction']),
			'row' => $WebApp->showTransactionRow($tokens,$fromAccount,false,'send'),
			'gas' => $gasBalance,
			// password salvata nel formato precedente: il browser la sostituisce con quella legata all'utente
			'prv_pas_upgraded' => $storedPassword['legacy'] ? WebApp::encryptForUser($passphrase, (int) Yii::$app->user->id) : null,
		];

		return $this->json($data);
 	}

	// cerca la ricevuta dal transaction hash
	// funzione invocata dal sw

	// testing::
	// curl -X POST -d 'id=S2hNeTVGQTkzWis0ekN3RDV3RVRmdz09' http://localhost:8080/index.php?r=wallet%2Fvalidate-transaction
	public function actionValidateTransaction()
	{
		set_time_limit(0); //imposto il time limit unlimited
		$WebApp = new WebApp;
		$requests = 1;
		$receipt = null;
		if (isset($_POST['pending']) && $_POST['pending'] == true){
			$maxrequests = 2;
		} else {
			$maxrequests = 30;
		}

		$tokens = Transactions::findOne($WebApp->decrypt($_POST['id'] ?? ''));
		if (null === $tokens || !$tokens->belongsToUser((int) Yii::$app->user->id)){
			throw new HttpException(404,'Transaction not found.');
		}
		$ERC20 = new Yii::$app->Erc20($tokens->id_user);

		while ($requests < $maxrequests)
		{
			$receipt = $ERC20->getReceipt($tokens->txhash);

			if ($receipt !== null){
				// ok, the transaction has mind in a new block
				break;
			}
			$requests ++;
			sleep(1);
		}
		if ($receipt === null){
			$data = [
				'id' => $_POST['id'], //NECESSARIO PER IL SALVATAGGIO IN  indexedDB quando ritorna al Service Worker
				'status' => $tokens->status,
				'success' => false,
			];
		} else {
			// esito dalla ricevuta: una transazione fallita sulla chain non è "complete"
			$succeeded = in_array(strtolower((string) ($receipt->status ?? '')), ['0x1', '1'], true);
			// passaggio atomico allo stato finale: anche l'indicizzatore wallet-realtime lo tenta (riga bloccata);
			// notifica solo chi lo fa davvero, così ogni trasferimento ha una sola coppia di notifiche
			$changed = Transactions::updateAll(
				['status' => $succeeded ? 'complete' : 'failed', 'blocknumber' => $receipt->blockNumber, 'token_received' => $succeeded ? $tokens->token_price : 0],
				['and', ['id' => $tokens->id], ['not in', 'status', ['complete', 'failed']]]
			);
			$tokens->refresh();

			$settings = Settings::poa($tokens->id_user);
		}
		if ($receipt !== null && $changed === 1 && $tokens->status === 'complete') {
			// notifica per chi ha inviato (from_address)
			$id_user_from = MPWallets::find()->userIdFromAddress($tokens->from_address);
			$notification = [
				'type' => 'token',
				'id_user' => $id_user_from,
				'status' => 'complete',
				'description' => Yii::t('app','A transaction you sent of {amount} {symbol} has been completed.',[
					'amount' => $tokens->token_price,
					'symbol' => $settings->smartContract->symbol,
				]),
				'url' => Url::to(['/transactions/view','id'=>WebApp::encrypt($tokens->id)],true),
				'timestamp' => time(),
				'price' => $tokens->token_price,
			];
			$pushOptions = NotificationService::notify($notification);

			// notifica per chi riceve (to_address)
			$id_user_to = MPWallets::find()->userIdFromAddress($tokens->to_address);

			// fix
			if (null !== $id_user_to){
				$notification['id_user'] = $id_user_to;
				$notification['description'] = Yii::t('app','You received a new transaction of {amount} {symbol}.',[
					'amount' => $tokens->token_price,
					'symbol' => $settings->smartContract->symbol,
				]);
				NotificationService::notify($notification);
			}
		}
		if ($receipt !== null) {
			//adesso posso uscire CON IL JSON DA REGISTRARE NEL SW.
			$balance = $ERC20->tokenBalance($tokens->from_address);
			$data = [
				'id' => $_POST['id'], //NECESSARIO PER IL SALVATAGGIO IN  indexedDB quando ritorna al Service Worker
				'status' => $tokens->status,
				'success' => true,
				'row' => $WebApp->showTransactionRow($tokens,$tokens->from_address,false,'send'),
				'balance' => $balance,
				'pushoptions' => $pushOptions ?? null,
				'formatted_balance' => $WebApp->si_formatter($balance),
			];

		}

		return $this->json($data);

	}

	private static function json ($data)
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}

}
