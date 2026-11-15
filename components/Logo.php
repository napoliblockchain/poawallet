<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\helpers\Url;


class Logo extends Component
{

  /*
  * In questa funzione che stampa a video il footer, la variabile
  * $color deve essere inserita nel formato: #ffdd1a
  */
  public static function footer($color=NULL){
      $versionfilename = Yii::$app->basePath."/../version.txt";
      if(file_exists($versionfilename)){
          $version = file_get_contents($versionfilename);
          $time = filemtime($versionfilename);
      }else{
          $version = "test";
          $time = time();
      }
      // una riga sobria: autore e versione (layout di accesso e procedura guidata)
      $footer = 'Made with ❤️ by ' . \yii\helpers\Html::a(\yii\helpers\Html::encode(\Yii::$app->params['adminName']), \Yii::$app->params['website'], ['target' => '_blank', 'rel' => 'noopener'])
          . '<br>Release n. ' . \yii\helpers\Html::encode(substr($version, 0, 7)) . ' ' . date('d/m/Y', $time);
      if ($color) $footer = '<span style="color:' . \yii\helpers\Html::encode($color) . ';">' . $footer . '</span>';

      return $footer;
  }

  public function login(){
    $logo = '
    <a href="'. Url::to(['site/index']) .'">
        <div style="padding: 0px;" >
            <img alt="logo" style="display: block; margin-left: auto;	margin-right: auto; max-width: 150px; " src="css/images/logo.png">
        </div>
    </a>
     ';
     echo $logo;
 }
 public function header(){
    $logo = '
    <a href="'. Url::to(['site/index']) .'">

        <img alt="logo" style="display: block; height:57px; margin-top:5px;" src="'. Yii::app()->request->baseUrl.Yii::app()->params['logoApplicazione'].'">

    </a>
    ';
    // <h1 class="logo-name">'.Yii::t('app','BOLT').' <small class="logo-descri">'.Yii::t('app','TTS wallet').'</small></h1>
    echo $logo;
}
}
