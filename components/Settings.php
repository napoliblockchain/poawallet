<?php
/**
* Questa classe carica le impostazioni della webapp
*/

namespace app\components;

use Yii;
use yii\base\Component;

use app\models\Owner;
use app\models\Host;

class Settings extends Component
{

    public static function owner(){
        return Owner::findOne(1);
    }

    // questa funziona ora assume come valore lo user id 
    /**
     * Rete e token del wallet (SIP-032): uguali per tutti gli utenti, dalle variabili d'ambiente (ChainConfig).
     * Il parametro resta per compatibilità con i chiamanti.
     */
    public static function poa($user_id = 0){
        return ChainConfig::get();
    }

    /**
     * chiavi VAPID per le notifiche push, da variabili d'ambiente
     * @return object|null public_key / private_key / subject, null se non configurate (push disattivate)
     */
    public static function vapid(){
        $vapid = Yii::$app->params['vapid'] ?? [];
        if (empty($vapid['public_key']) || empty($vapid['private_key'])) {
            return null;
        }
        return (object) $vapid;
    }

    public static function host(){
        return Host::findOne(1);
    }

}
