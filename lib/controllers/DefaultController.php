<?php

namespace infinite\deferred\controllers;

use infinite\action\Interaction;
use infinite\deferred\components\LogResult;
use infinite\deferred\components\ServeableResultInterface;
use infinite\deferred\models\DeferredAction;
use Yii;
use yii\web\NotFoundHttpException;

class DefaultController extends \infinite\web\Controller
{
    public function actionNavPackage()
    {
        $navPackage = Yii::$app->getModule('deferredAction')->navPackage();
        Yii::$app->response->data = $navPackage;
    }
    public function actionDownload()
    {
        if (!isset($_GET['id']) || !($deferredAction = DeferredAction::findMine()->andWhere(['id' => $_GET['id']])->one())) {
            throw new NotFoundHttpException("Deferred action not found!");
        }
        $action = $deferredAction->actionObject;
        if (!($action->result instanceof ServeableResultInterface)) {
            throw new NotFoundHttpException("Deferred action does not have a serveable result");
        }
        $action->result->serve();
    }

    public function actionViewLog()
    {
        if (!isset($_GET['id']) || !($deferredAction = DeferredAction::findMine()->andWhere(['id' => $_GET['id']])->one())) {
            throw new NotFoundHttpException("Deferred action not found!");
        }
        $action = $deferredAction->actionObject;
        if (!($action->result instanceof LogResult)) {
            throw new NotFoundHttpException("Deferred action does not have a serveable result");
        }
        $this->params['deferredAction'] = $deferredAction;
        $this->params['action'] = $action;
        if (!empty($_GET['package'])) {
            Yii::$app->response->data = $deferredAction->package(true);

            return;
        }
        Yii::$app->response->task = 'message';
        Yii::$app->response->taskOptions = ['title' => $action->descriptor . ' on ' . date("F d, Y g:i:sa", strtotime($deferredAction->created)), 'modalClass' => 'modal-xl'];
        Yii::$app->response->view = 'viewLog';
    }

    public function actionCancel()
    {
        if (!isset($_GET['id']) || !($deferredAction = DeferredAction::findMine()->andWhere(['id' => $_GET['id']])->one())) {
            throw new NotFoundHttpException("Deferred action not found!");
        }
        $action = $deferredAction->actionObject;
        if ($deferredAction->status === 'queued') {
            if ($deferredAction->cancel()) {
                Yii::$app->response->task = 'message';
                Yii::$app->response->content = 'Task was canceled!';
                Yii::$app->response->taskSet = [['task' => 'deferredAction']];
                Yii::$app->response->taskOptions = ['state' => 'success'];

                return;
            }
        }
        Yii::$app->response->task = 'message';
        Yii::$app->response->content = 'Task could not be canceled.';
        Yii::$app->response->taskOptions = ['state' => 'danger'];
    }

    public function actionDismiss()
    {
        if (!isset($_GET['id']) || !($deferredAction = DeferredAction::findMine()->andWhere(['id' => $_GET['id']])->one())) {
            throw new NotFoundHttpException("Deferred action not found!");
        }
        $action = $deferredAction->actionObject;
        if (in_array($deferredAction->status, ['success', 'error'])) {
            if ($deferredAction->dismiss()) {
                // Yii::$app->response->task = 'message';
                // Yii::$app->response->content = 'Task was dismissed!';
                Yii::$app->response->taskSet = [['task' => 'deferredAction']];
                //Yii::$app->response->taskOptions = ['state' => 'warning'];
                return;
            }
        }
        Yii::$app->response->task = 'message';
        Yii::$app->response->content = 'Task could not be dismissed.';
        Yii::$app->response->taskOptions = ['state' => 'danger'];
    }

    public function actionResolveInteraction()
    {
        if (!isset($_POST['id']) || !isset($_POST['value']) || !Interaction::saveResolution($_POST['id'], $_POST['value'])) {
            Yii::$app->response->task = 'message';
            Yii::$app->response->content = 'Resolution could not be saved';
            Yii::$app->response->taskOptions = ['state' => 'danger'];

            return;
        }
        Yii::$app->response->success = 'Success';
    }
}
