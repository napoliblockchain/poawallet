<?php namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\web\HttpException;
use yii\filters\VerbFilter;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

use app\models\MPWallets;
use app\models\Transactions;
use app\models\SmartContracts;
use app\models\Ethtxs;
use app\models\EthtxsStatus;
use app\models\Nodes;

use yii\helpers\Json;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

use app\components\Settings;
use app\components\WebApp;
use app\components\NotificationService;
use app\components\Erc20;

class BlockchainController extends Controller
{
	public $transactionsFound = [];
	public $logFileName;

	private function setTransactionsFound($transaction){
        $this->transactionsFound[] = $transaction;
	}
	private function getTransactionsFound(){
        return $this->transactionsFound;
	}


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

	public function actionEthtx()
	{
		$raw_post_data = file_get_contents('php://input');
        $_POST = Json::decode($raw_post_data);
		// always the logged user: the encrypted user_id sent by the client is not trusted
		$user_id = Yii::$app->user->id;
		// echo '<pre>'.print_r($_POST,true).'</pre>';

		$wallets = MPWallets::find()->where(['id_user'=>$user_id])->one();
		if (null === $wallets){
			return Json::encode(['success'=>false,'message'=>'Wallet is null']);
		}
		$node = Nodes::find()->where(['id_user'=>$user_id])->one();
		if (null === $node){
			return Json::encode(['success'=>false,'message'=>'Node is null']);
		}
		$ethTxsStatus = EthtxsStatus::find()->where(['symbol'=>$node->blockchain->symbol])->one();
		if (null === $ethTxsStatus){
			return Json::encode(['success'=>false,'message'=>'EthtxsStatus is null']);
		}

		$fromAddress = $wallets->wallet_address;
		$timeToComplete = 0;
		$searchBlock = $ethTxsStatus->blocknumber;

		// echo $wallets->blocknumber;
		// exit;

		$ethtxs = Ethtxs::find()
            ->orwhere(['=','txfrom', $fromAddress])
            ->orwhere(['=','txto', $fromAddress])
			->andWhere(['>=','dec_blocknumber',hexdec($wallets->blocknumber)])
            ->all();

		// echo '<pre>'.print_r($ethtxs,true).'</pre>';
		// exit;
		foreach ($ethtxs as $data){
			// opero solo se è una transazione token
			if ($data->contract_to != ''){
				$smartContracts = SmartContracts::find()->where(['smart_contract_address'=>$data->contract_to])->one();

				if (null === $smartContracts)
					continue;

				// cerco la transazione nella tabella transactions tramite txhash
				$tokens = Transactions::find()->findByHash($data->txhash);

				if (null === $tokens){
					// ho trovato una transazinoe che non è nel DB.
					$this->saveAndSendPushMessages([
						'userid' => $user_id,
						'smartContracts' => $smartContracts,
						'data' => $data,
						'fromAddress' => $fromAddress,
						'tokens' => null,
						'receiver' => true,
						'sender' => true,
					]);
				}else{
					// ho trovato la tx in db ma non ho ancora
					// aggiornato il ricevente...
					$this->saveAndSendPushMessages([
						'userid' => $user_id,
						'smartContracts' => $smartContracts,
						'data' => $data,
						'fromAddress' => $fromAddress,
						'tokens' => $tokens,
						'receiver' => ($tokens->token_received == 0) ? true : false,
						'sender' => ($tokens->status == 'new') ? true : false,
					]);
				}
			}
		}
		$wallets->blocknumber = $searchBlock;
		$wallets->save();

		$txFound = $this->getTransactionsFound();
		$chainBlock = $this->getChainBlock($user_id);
		$walletBlock = $this->getChainBlock($user_id, $searchBlock);
		$timeToComplete = hexdec($chainBlock->timestamp) - hexdec($walletBlock->timestamp);

		// echo '<pre>'.print_r($walletBlock,true).'</pre>';exit;

		$difference = hexdec($chainBlock->number) - hexdec($searchBlock);
        $percentageCompletion = round(hexdec($searchBlock) / hexdec($chainBlock->number) * 100, 4);

	   	$return = [
			'success'=>true,
			"transactions"=>$txFound,
			'searchFromBlockNumber' => $searchBlock,
            "walletBlocknumber"=> $wallets->blocknumber,
            "chainBlocknumber"=> $chainBlock->number,
            "headerMessage"=> Yii::t('app', "{n} blocks left.", ['n' => $difference]),
            "difference"=> $difference,
            "user_address"=> $fromAddress,
            "relativeTime" => Yii::$app->formatter->asDuration($timeToComplete),
            "percentageCompletion" => $percentageCompletion."%",
            "latestBlockHash"=>Html::a($chainBlock->hash,$node->blockchain->url_block_explorer.'/block/'.hexdec($chainBlock->number),['target'=>'_blank']),
            "walletBlockHash"=>Html::a($walletBlock->number,$node->blockchain->url_block_explorer.'/block/'.hexdec($searchBlock),['target'=>'_blank']),
	   	];
	   	return Json::encode($return);
	}

	private function getChainBlock($userid,$number='latest',$details=false){
		$ERC20 = new Yii::$app->Erc20($userid);
		$x = 0;
		$limit = 30;
		while (true){
			$x++;
			$blockInfo = $ERC20->getBlockInfo($number,$details);
			// echo '<pre> blockinfo: '.print_r($blockInfo,true).'</pre>';exit;

			if (null !== $blockInfo){
				return $blockInfo;
				// break;
			}
			if ($x > $limit){
				$return = (object) [
					'number'=>'0x0000000000000000000000000000000000000000000000000000000000000001',
					'timestamp' => time(),
				];
				return $return;
				// break;
			}
		}
	}


	private function saveAndSendPushMessages($array){
		// echo '<pre>'.print_r($array,true).'</pre>';
		// exit;
		$donothing = false;
		$decimals = $array['smartContracts']->decimals;

		$amount = $array['data']->contract_value / pow(10, $decimals);
		// echo '<pre>'.print_r($amount,true).'</pre>';
		// exit;
		// $amount = $array['data']->contract_value / pow(10, $array['smartContracts']->decimals);
		$sender_notification = $array['sender'];
		$receiver_notification = $array['receiver'];

		if (null === $array['tokens']){
			$tokens = new Transactions;
			$tokens->id_user = $array['userid'];
			$tokens->status	= 'complete';
			$tokens->type	= 'token';
			$tokens->id_smart_contract = $array['smartContracts']->id; //$settings->smartContract->id;
			$tokens->token_price	= $amount;
			$tokens->token_received	= $amount;
			$tokens->invoice_timestamp = $array['data']->timestamp;
			$tokens->expiration_timestamp = $array['data']->timestamp + 60*15;
			$tokens->from_address = $array['data']->txfrom;
			$tokens->to_address = $array['data']->txto;
			$tokens->blocknumber = $array['data']->blocknumber;
			$tokens->txhash = $array['data']->txhash;
			$tokens->message = '';
		} else {
			$tokens = $array['tokens'];
			if ($tokens->token_received == 0){
				$tokens->status	= 'complete';
				$tokens->token_received	= $amount;
			} else {
				$donothing = true;
			}
		}
		$tokens->save();

		if (!$donothing){
			$dateLN = date("d M `y",$tokens->invoice_timestamp);
			$timeLN = date("H:i:s",$tokens->invoice_timestamp);

			$id_user_from = MPWallets::find()->userIdFromAddress($tokens->from_address);

			if ($id_user_from !== null && $sender_notification == true){
				// notifica per chi ha inviato (from_address)
				$notification = [
					'type' => 'token',
					'id_user' => $id_user_from,
					'status' => $tokens->status,
					'description' => Yii::t('app','A transaction you sent of {amount} {symbol} has been completed.',[
						'amount' => WebApp::si_formatter($tokens->token_price),
						'symbol' => $array['smartContracts']->symbol, //$settings->smartContract->symbol,
					]),
					'url' => Url::to(['/transactions/view','id'=>WebApp::encrypt($tokens->id)],true),
					'timestamp' => $tokens->invoice_timestamp,
					'price' => $tokens->token_price,
				];

				$messages= NotificationService::notify($notification);
			}
			// notifica per chi riceve (to_address)
			$id_user_to = MPWallets::find()->userIdFromAddress($tokens->to_address);
			if ($id_user_to !== null && $receiver_notification == true){
				$notification = [
					'type' => 'token',
					'id_user' => $id_user_to,
					'status' => $tokens->status,
					'description' => Yii::t('app','You received a new transaction of {amount} {symbol}.',[
						'amount' => WebApp::si_formatter($tokens->token_price),
						'symbol' => $array['smartContracts']->symbol, //$settings->smartContract->symbol,
					]),
					'url' => Url::to(['/transactions/view','id'=>WebApp::encrypt($tokens->id)],true),
					'timestamp' => $tokens->invoice_timestamp,
					'price' => $tokens->token_price,
				];
				$messages= NotificationService::notify($notification);
			}

			$ERC20 = new Yii::$app->Erc20($array['userid']);

			// imposto l'array contenente le transazioni e che sarà restituito alla funzione chiamante
			$this->setTransactionsFound([
				'id_token' => $tokens->id,
				'pushoptions' => $messages ?? null,
				'balance' => WebApp::si_formatter($ERC20->tokenBalance($array['fromAddress'])),
				'row' => WebApp::showTransactionRow($tokens,$array['fromAddress'],true),
			]);

		}

	}

	// verifica eventuali transazioni dell'utente a partire dal getBlockNumber
	// dell'utente fino ad arrivare all'ultimo blocco della blockchain
	public function actionGetBlocknumber(){
		$wallet = MPWallets::find()
				->andWhere(['id_user'=>Yii::$app->user->id])
				->one();

		$return = [
			 'id'=>time(),
			 "walletBlocknumber"=>0,
			 "chainBlocknumber"=>0,
			 "difference"=>0,
			 "headerMessage"=>'',
			 "my_address"=>$wallet->wallet_address,
			 "relativeTime" =>'',
			 "success"=>false,
		];

		$ERC20 = new Yii::$app->Erc20(1);

		$blockLatest = $ERC20->getBlockInfo();
		// $blockWallet = Yii::$app->Erc20->getBlockInfo($wallet->blocknumber);
		// print_r($block);
		// exit;

		//calcolo la differenza tra i blocchi
		$difference = hexdec($blockLatest->number) - hexdec($wallet->blocknumber);

		$return = [
			 'id'=>time(),
			 "walletBlocknumber"=>$wallet->blocknumber,
			 "chainBlocknumber"=>$blockLatest->number,
			 "headerMessage"=> Yii::t('app', "{n} blocks left.", ['n' => $difference]),
			 "difference"=> $difference,
			 "my_address"=>$wallet->wallet_address,
			 "relativeTime" => Yii::$app->formatter->asDuration($difference),
			 "success"=>true,
		];

		return $this->json($return);

	}

	//scrive nel file log le informazioni richieste
    private function log($text){
        //$filename = Yii::$app->basePath."/logs/blockchain-latest.log";
        $handlefile = fopen($this->logFileName, "a");

		$time = "\r\n" .date('Y/m/d h:i:s a - ', time());
		fwrite($handlefile, $time.$text);
    }






	private static function json ($data)
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}







}
