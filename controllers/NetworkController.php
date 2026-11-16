<?php

namespace app\controllers;

use app\components\Settings;
use yii\filters\AccessControl;
use yii\web\Controller;

class NetworkController extends Controller
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
	 * Show new wallet page
	 */
	public function actionIndex()
 	{
 		return $this->render('index', [
			'node' => Settings::poa(),
		]);
 	}

}
