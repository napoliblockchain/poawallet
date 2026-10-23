<?php

namespace app\controllers;

use Yii;
use app\models\Nodes;
use app\models\search\NodesSearch;
use app\models\SmartContracts;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * Node management entrypoint.
 * Keeps tokenpay-compatible routes while reusing poawallet settings UI.
 */
class NodesController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
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
     * Minimal index for compatibility: exposes provider and then uses update flow.
     */
    public function actionIndex()
    {
        $searchModel = new NodesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->andWhere(['id_user' => Yii::$app->user->id]);

        // poawallet currently has only one active node selection per user.
        return $this->redirect(['update']);
    }

    public function actionView($id)
    {
        // Current UX does not include a dedicated node detail page.
        // Keep route available for compatibility.
        return $this->redirect(['update']);
    }

    public function actionUpdate()
    {
        $model = $this->findModel();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('nodeSelectedsuccess', Yii::t('app', 'Node selected succesfully!'));
        }

        return $this->render('/settings/nodes/update', [
            'model' => $model,
        ]);
    }

    public function actionDelete($id)
    {
        // reserved for future use
        return $this->redirect(['update']);
    }

    protected function findModel()
    {
        $model = Nodes::find()->byUserId(Yii::$app->user->id)->one();

        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }

    public function actionTokensList($id)
    {
        $smartcontract = ArrayHelper::map(
            SmartContracts::find()
                ->byUserId(Yii::$app->user->id)
                ->byBlockchainId($id)
                ->all(),
            'id',
            'denomination'
        );

        return Json::encode($smartcontract);
    }
}
