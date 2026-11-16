<?php

namespace app\controllers;

use yii\web\Controller;
use yii\filters\AccessControl;

/**
 * UsersController implements the CRUD actions for Users model.
 */
class SettingsController extends Controller
{
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
     * Displays a single BoltSocialusers model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionIndex()
    {
        return $this->render('index');
    }
}
