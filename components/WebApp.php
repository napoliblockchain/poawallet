<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\httpclient\Client;

use app\models\Nodes;
use app\models\Blockchains;
use app\models\Notifications;

class WebApp extends Component
{
    // /**
    //  * Recupera casualmente il primo nodo POA disponibile
    //  * @return nodeurl
    //  */
    // public static function getPoaNode($blockchain_id = 1)
    // {
    //     $blockchain = Blockchains::findOne($blockchain_id);
    //     // $nodes = $blockchain->nodes;
    //     //
    //     // $nodelist = ArrayHelper::map(
    //     //     $nodes,
    //     //     'id_node',
    //     //     function ($item, $defaultValue) {
    //     //         return $item->url . ':' . $item->port;
    //     //     }
    //     // );
    //     //
    //     // $isdown = true;
    //     // shuffle($nodelist);
    //     // do {
    //     //     $node = array_shift($nodelist);
    //     //
    //     //     if (empty($node)){
    //     //         return false;
    //     //     }
    //     //
    //     //     if( self::checkUrl( $node ) ) {
    //     //         $isdown = false;
    //     //     }
    //     // } while ($isdown);
    //
    //     // return $node;
    //
    //     if( self::checkUrl( $blockchain->url ) ) {
    //         return false;
    //     }
    //     // echo $blockchain->url;
    //     // exit;
    //     return $blockchain->url;
    // }
    /**
	 * PHP/cURL function to check a web site status. If HTTP status is not 200 or 302, or
	 * the requests takes longer than 1 seconds, the website is unreachable.
	 *
	 * Follow me on Twitter: @sergiocasizzone
	 *
	 * @param string $url URL that must be checked
	 * @param integer $timeout Seconds to timeout
	 */
	function checkUrl( $url, $timeout = 1 ) {
		$ch = curl_init();

		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, $timeout);

		$http_respond = curl_exec($ch);
		$http_respond = trim( strip_tags( $http_respond ) );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		if ( ( $http_code == "200" )
			|| ( $http_code == "201" )
			|| ( $http_code == "302" )
		) {
			return true;
		} else {
			return false;
		}
		curl_close( $ch );
	}

    // public static function checkUrl($url) {
    //     $client = new Client();
    //
    //     $request = $client->createRequest()
    //         ->setMethod('GET')
    //         ->setUrl($url)
    //         ->setOptions([
    //             'timeout' => 1, // set timeout to 1 seconds for the case server is not responding
    //         ])
    //         ->send();
    //
    //     return $request->getisOk();
    // }

	private static function si_classifier($val){
		$suffixes = [
			24 => [
					'long_suffix' => 'yotta',
					'short_suffix' => 'Y',
					'scalar' => gmp_strval('1000000000000000000000000')
			],
			21 => [
					'long_suffix' => 'zetta',
					'short_suffix' => 'Z',
					'scalar' => gmp_strval('1000000000000000000000')
			],
			18 => [
					'long_suffix' => 'exa',
					'short_suffix' => 'E',
					'scalar' => pow(1000, 6)
			],
			15 => [
					'long_suffix' => 'peta',
					'short_suffix' => 'P',
					'scalar' => pow(1000, 5)
			],
			12 => [
					'long_suffix' => 'tera',
					'short_suffix' => 'T',
					'scalar' => pow(1000, 4)
			],
			9 => [
					'long_suffix' => 'giga',
					'short_suffix' => 'G',
					'scalar' => pow(1000, 3)
			],
			6 => [
					'long_suffix' => 'mega',
					'short_suffix' => 'M',
					'scalar' => pow(1000, 2)
			],
			3 => [
					'long_suffix' => 'kilo',
					'short_suffix' => 'k',
					'scalar' => pow(1000, 1)
			],
			0 => [
					'long_suffix' => '',
					'short_suffix' => '',
					'scalar' => pow(1000, 0)
			],
			-3 => [
					'long_suffix' => 'milli',
					'short_suffix' => 'm',
					'scalar' => bcmul('1', '0.001', 10)
			],
			-6 => [
					'long_suffix' => 'micro',
					'short_suffix' => 'µ',
					'scalar' => bcmul('1', '0.000001', 10)
			],
			-9 => [
					'long_suffix' => 'nano',
					'short_suffix' => 'n',
					'scalar' => bcmul('1', '0.000000001', 10)
			],
			-12 => [
					'long_suffix' => 'pico',
					'short_suffix' => 'p',
					'scalar' => bcmul('1', '0.000000000001', 13)
			],
			-15 => [
					'long_suffix' => 'femto',
					'short_suffix' => 'n',
					'scalar' => bcmul('1', '0.000000000000001', 16)
			],
			-18 => [
					'long_suffix' => 'atto',
					'short_suffix' => 'a',
					'scalar' => bcmul('1', '0.000000000000000001', 19)
			],
			-21 => [
					'long_suffix' => 'zepto',
					'short_suffix' => 'z',
					'scalar' => bcmul('1', '0.000000000000000000001', 22)
			],
			-24 => [
					'long_suffix' => 'yopto',
					'short_suffix' => 'y',
					'scalar' => bcmul('1', '0.000000000000000000000001', 25)
			],

		];
		$exponent = intval(floor(log10(abs($val))/3.0)*3);
		return $suffixes[$exponent] ?? null;
	}


	// Best shortens a number and attaches K, M, B, etc. accordingly
	public static function si_formatter($number, $style = 'short'){
		$classifier = self::si_classifier($number);
		if ($classifier === null){
			# Don't know how to classify this value
		    return $number;
		}
		$scaled = round($number / $classifier['scalar'], 3);

		if ($style == 'short'){
			return $scaled.' '.$classifier['short_suffix'];
		} else {
			return $scaled.' '.$classifier['long_suffix'];
		}

	}

	// Shortens a number and attaches K, M, B, etc. accordingly
    // public function number_shorten($number, $precision = 3, $divisors = null) {
    //     // Setup default $divisors if not provided
    //     if (!isset($divisors)) {
    //         $divisors = array(
	// 			pow(1000, -4) => 'p', // 0.000000000001 pico
	// 			pow(1000, -3) => 'n', // 0.000000001 nano
	// 			pow(1000, -2) => 'µ', // 0.000001 micro
	// 			pow(1000, -1) => 'm', // 0.001 milli
	// 			pow(1000, 0) => '', // 1000^0 == 1
    //             pow(1000, 1) => 'k', // Thousand
    //             pow(1000, 2) => 'M', //Yii::t('app','Million'), // Mega - Million
    //             pow(1000, 3) => 'G', //Yii::t('app','Billion'), // Giga - Billion
    //             pow(1000, 4) => 'T', //Yii::t('app','Trillion'), // Tera - Trillion
    //             pow(1000, 5) => 'P', //Yii::t('app','Quadrillion'), // Peta - Quadrillion
    //             pow(1000, 6) => 'E',// Yii::t('app','Quintillion'), // Exa - Quintillion
    //             gmp_strval('1000000000000000000000') => 'Z', //Yii::t('app','Sextillion'), // Sextillion
    //             gmp_strval('1000000000000000000000000') => 'Y', //Yii::t('app','Septillion'), // Septillion
    //         );
    //     }
	//
    //     // Loop through each $divisor and find the
    //     // lowest amount that matches
    //     foreach ($divisors as $divisor => $shorthand) {
    //         if (abs($number) < ($divisor * 1000)) {
    //             // We found a match!
    //             break;
    //         }
    //     }
    //     // We found our match, or there were no matches.
    //     // Either way, use the last defined value for $divisor.
    //     return round(number_format($number / $divisor, $precision),$precision) .' '. $shorthand;
    // }

    /**
     * funzione che crypta un testo
     * @param $text il testo da criptare
     * @return testo criptato
     */

    public static function encrypt($data) {
        return strtr(self::enc($data,  hash( 'sha256', self::encryptionSecret('secret_key') )), '', '');
    }

    public static function decrypt($data) {
        return strtr(self::dec($data,  hash( 'sha256', self::encryptionSecret('secret_key') )), '', '');
    }

    /**
     * cifra un valore legandolo all'utente: solo lo stesso utente potra decifrarlo
     * @return string formato "v2." + testo cifrato
     */
    public static function encryptForUser($value, int $userId): string
    {
        return 'v2.' . self::encrypt(Json::encode(['u' => $userId, 'v' => (string) $value]));
    }

    /**
     * decifra un valore solo se appartiene all'utente indicato
     * Formato precedente (non legato all'utente) accettato solo se params['crypto.acceptLegacy'] e true,
     * per migrare i dati gia salvati nei browser.
     * @return array|null ['value' => string, 'legacy' => bool], null se non valido o di un altro utente
     */
    public static function decryptForUser($data, int $userId): ?array
    {
        if (!is_string($data) || $data === '') {
            return null;
        }
        if (strncmp($data, 'v2.', 3) === 0) {
            $plain = self::decrypt(substr($data, 3));
            $payload = is_string($plain) ? json_decode($plain, true) : null;
            if (!is_array($payload) || !isset($payload['u'], $payload['v']) || (int) $payload['u'] !== $userId) {
                return null;
            }
            return ['value' => (string) $payload['v'], 'legacy' => false];
        }
        if (empty(Yii::$app->params['crypto.acceptLegacy'])) {
            return null;
        }
        $plain = self::decrypt($data);
        if (!is_string($plain) || $plain === '') {
            return null;
        }
        return ['value' => $plain, 'legacy' => true];
    }

    /**
     * Metodi privati della classe
     */
    private static function enc($data, $key) {
        return base64_encode( openssl_encrypt( $data, self::encryptionSecret('encrypt_method'), $key, 0, substr( hash( 'sha256', self::encryptionSecret('secret_iv') ), 0, 16 ) ) );
    }

    private static function dec($data, $key) {
        return openssl_decrypt( base64_decode( $data ), self::encryptionSecret('encrypt_method'), $key, 0, substr( hash( 'sha256', self::encryptionSecret('secret_iv') ), 0, 16 ) );
    }

    /**
     * legge secret_key, secret_iv o encrypt_method dai params (variabili d'ambiente ENCRYPT_*)
     * @param $key la chiave da leggere
     * @return restituisce la chiave letta
     */
    private static function encryptionSecret($key)
    {
        $value = Yii::$app->params['encryption'][$key] ?? '';

        if ($value === '' || $value === null) {
            throw new \RuntimeException('Missing encryption configuration: ' . $key);
        }

        return $value;
    }

    /** Saldo token da unità minime a decimale leggibile (es. 1234 con 2 decimali -> 12,34). */
    public static function tokenAmount($units, int $decimals): string
    {
        return Yii::$app->formatter->asDecimal($units / (10 ** $decimals), null,
            [\NumberFormatter::MIN_FRACTION_DIGITS => 0, \NumberFormatter::MAX_FRACTION_DIGITS => $decimals]);
    }

    /**
     * Riga di una transazione (elenchi, pagina invio, aggiornamenti di wallet-sync.js).
     * Indirizzi, simbolo, stato e messaggio arrivano da dati utente o dalla chain: sempre codificati.
     */
    public static function showTransactionRow($data, $fromAddress, $newTransaction = false, $status = null)
    {
        // indirizzi minuscoli o con checksum EIP-55 (righe dell'indicizzatore): confronto senza maiuscole
        $outgoing = strcasecmp((string) $data->from_address, (string) $fromAddress) === 0;
        $amount = Yii::$app->formatter->asDecimal($data->token_price);
        if ($data->status == 'new' && $data->txhash != '') {
            $data->status = 'pending';
            $status = true;
        }
        $counterpart = $outgoing ? $data->to_address : $data->from_address;
        $label = $outgoing ? Yii::t('app', 'To: ') : Yii::t('app', 'From: ');
        $meta = Html::encode(Yii::$app->formatter->asDatetime((int) $data->invoice_timestamp, 'php:d/m/Y H:i'));
        if ($status !== null) {
            $meta .= ' <span class="badge ' . ($data->status == 'complete' ? 'bg-success-lt' : 'bg-secondary-lt') . ' text-capitalize ms-1" id="transaction-status-'
                . self::encrypt($data->id) . '">' . Html::encode($data->status) . '</span>';
        }
        $message = $data->message != '' ? '<div class="small text-secondary text-truncate"><i class="far fa-comment me-1" aria-hidden="true"></i>' . Html::encode($data->message) . '</div>' : '';
        return Html::a(
            '<span class="wallet-tx-icon"><i class="fas ' . ($outgoing ? 'fa-arrow-up' : 'fa-arrow-down') . '" aria-hidden="true"></i></span>'
            // indirizzo accorciato come sul POS (inizio…fine), completo al passaggio del mouse
            . '<span class="wallet-tx-body"><span class="d-block text-truncate" title="' . Html::encode($counterpart) . '">'
            . Html::encode($label . (strlen((string) $counterpart) > 24 ? substr($counterpart, 0, 12) . '…' . substr($counterpart, -10) : $counterpart)) . '</span>'
            . '<span class="d-block small text-secondary">' . $meta . '</span>' . $message . '</span>'
            . '<span class="wallet-tx-amount">' . ($outgoing ? '&minus; ' : '+ ') . Html::encode($amount) . ' <small>' . Html::encode($data->smartContract->symbol) . '</small></span>',
            ['/transactions/view', 'id' => self::encrypt($data->id)],
            ['class' => 'wallet-tx ' . ($outgoing ? 'wallet-tx-out' : 'wallet-tx-in') . ($newTransaction ? ' bg-newtransaction' : '')]
        );
    }

    public static function Icon($type_notification,$type_transaction=null){
        switch (strtolower(trim($type_notification))){
            //ricordati i case tutti minuscoli
            case 'help':
                $zmdicon = 'fa fa-question';
                break;

                case 'alarm':
                    $zmdicon = 'fa fa-exclamation';
                    break;


            // case 'fattura':
            //     $zmdicon = 'zmdi zmdi-collection-pdf';
            //     break;
            case 'invoice':
                $zmdicon = 'fab fa-btc';
                break;

            case 'token':
                $zmdicon = 'fa fa-star';
                break;

                case 'ether':
                    $zmdicon = 'fab fa-ethereum';
                    break;

            // case 'withdraw':
            //     $zmdicon = 'zmdi zmdi-balance';
            //     break;

            // case 'deposit':
            //     $zmdicon = 'fa fa-eur';
            //     break;

            case  'contact':
              $zmdicon = 'fa fa-users';
              break;


            case 'new':
            case 'complete':
            // case 'paid':
            // case 'confirmed':
            // case 'invoice':
              $zmdicon = 'fa fa-star';
                // $zmdicon = 'fab fa-btc';
                break;


            // case 'expired':
            // case 'invalid':
            // case 'failed':
            //     $zmdicon = 'fa fa-exclamation';
            //     break;

            default:
                $zmdicon = 'fa fa-info';
        }
        return $zmdicon;
    }
    public static function Color($status){
        switch (strtolower(trim($status))){
            //ricordati i case tutti minuscoli
            case 'alarm':
                $color = 'bg-danger';
                break;

            case 'help':
                $color = 'bg-success';
                break;

            case 'fattura':
            case 'sending':
            case 'new':
                $color = 'bg-dark';
                break;

            case 'failed':
            case 'invalid':
                $color = 'bg-danger';
                break;

            case 'expired':
            case 'paidpartial':
                $color = 'bg-warning';
                break;

            case 'complete':
            case 'paid':
            case 'confirmed':
            case 'sent':
                $color = 'bg-success';
                break;

            case 'paidover':
                $color = 'bg-primary';
                break;

          case 'followed':
              $color = "bg-success";
              break;

          case 'unfollowed':
                  $color = "bg-warning";
                  break;

            default:
                $color = 'bg-secondary';
        }
        return $color;
    }

    /**
	* Questa funziona mostra a video il tipo di transazione e se è stata inviata o ricevuta
	*/
	public function typePrice($price,$sent){
		return ($sent == 'sent' ? '<h5 class="text-warning">-' : '<h5 class="text-success">+') . $price . '</h5>';
	}

    /** Riga di una notifica (elenco messaggi): testo e link codificati; vecchi link index.php?r= resi assoluti. */
    public static function showMessagesRow($item)
    {
        $data = Notifications::findOne($item->id_notification);
        if ($data === null) return '';
        $icon = self::Icon(strpos((string) $data->description, 'token') !== false ? 'token' : $data->type);
        $url = (string) $data->url;
        if (str_starts_with($url, 'index.php')) $url = '/' . $url;
        $badge = ['complete' => 'bg-success-lt', 'expired' => 'bg-warning-lt'][$data->status] ?? 'bg-secondary-lt';
        return Html::a(
            '<span class="wallet-tx-icon bg-primary-lt text-primary"><i class="' . Html::encode($icon) . '" aria-hidden="true"></i></span>'
            . '<span class="wallet-tx-body"><span class="d-block text-break">' . Html::encode(Yii::t('app', (string) $data->description)) . '</span>'
            . '<span class="d-block small text-secondary">' . Html::encode(Yii::$app->formatter->asDatetime((int) $data->timestamp, 'php:d/m/Y H:i'))
            . ($data->status ? ' <span class="badge ' . $badge . ' text-capitalize ms-1">' . Html::encode($data->status) . '</span>' : '') . '</span></span>',
            preg_match('~^(/|https?://)~i', $url) ? $url : '#',
            ['class' => 'wallet-tx']
        );
    }
}
