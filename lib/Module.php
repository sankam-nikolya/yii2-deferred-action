<?php
namespace infinite\deferred;

use infinite\deferred\models\DeferredAction;
use Yii;
use yii\base\Application;
use yii\base\Event;
use yii\helpers\Url;

/**
 * Module [[@doctodo class_description:infinite\deferred\Module]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class Module extends \yii\base\Module
{
    /**
     * @var [[@doctodo var_type:_active]] [[@doctodo var_description:_active]]
     */
    protected $_active;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $app = Yii::$app;
        if ($app instanceof \yii\web\Application) {
            $app->getUrlManager()->addRules([
                $this->id . '/<action:[\w\-]+>' => $this->id . '/default/<action>',
            ], false);
        }
    }

    /**
     * [[@doctodo method_description:daemonPostTick]].
     */
    public function daemonPostTick()
    {
        if (isset($this->_active)) {
            $lastError = error_get_last();
            $lastErrorMessage = '';
            if (isset($lastError['file'])) {
                $lastErrorMessage .= $lastError['file'];
            }
            if (isset($lastError['line'])) {
                $lastErrorMessage .= ':' . $lastError['line'];
            }
            if (isset($lastError['message'])) {
                $lastErrorMessage .= ' :' . $lastError['message'];
            }
            $this->_active->status = 'error';
            $this->_active->actionObject->result->message .= ' Runner Error: ' . $lastErrorMessage;
            $this->_active->save();
        }
    }

    /**
     * [[@doctodo method_description:daemonTick]].
     */
    public function daemonTick($event)
    {
        $this->handleOneQueued();
    }

    /**
     * [[@doctodo method_description:daemonPriority]].
     */
    public function daemonPriority($event)
    {
        $running = DeferredAction::find()->where(['status' => 'running'])->orderBy(['priority' => SORT_DESC, 'created' => SORT_ASC])->all();
        foreach ($running as $action) {
            $action->status = 'error';
            $action->save();
        }
    }

    /**
     * [[@doctodo method_description:pickOneQueued]].
     *
     * @return [[@doctodo return_type:pickOneQueued]] [[@doctodo return_description:pickOneQueued]]
     */
    protected function pickOneQueued()
    {
        return DeferredAction::find()->where(['status' => 'queued'])->orderBy(['priority' => SORT_DESC, 'created' => SORT_ASC])->one();
    }

    /**
     * [[@doctodo method_description:handleOneQueued]].
     */
    protected function handleOneQueued()
    {
        $queued = $this->pickOneQueued();
        if ($queued) {
            try {
                $queued->run();
            } catch (\Exception $e) {
                $queued = DeferredAction::find()->where(['id' => $queued->id])->one();
                if ($queued) {
                    $queued->status = 'error';
                    $message = $e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage();
                    $queued->actionObject->result->message .= ' Runner Exception: ' . $message;
                    $queued->actionObject->result->handleException($e);
                    $queued->save();
                }
            }
        }
    }

    /**
     * [[@doctodo method_description:cleanActions]].
     */
    public function cleanActions($event)
    {
        $items = DeferredAction::find()->where(['and', '`expires` < NOW()', '`status`=\'success\''])->all();
        foreach ($items as $item) {
            $item->dismiss(false);
        }
    }

    /**
     * [[@doctodo method_description:navPackage]].
     *
     * @return [[@doctodo return_type:navPackage]] [[@doctodo return_description:navPackage]]
     */
    public function navPackage()
    {
        $package = ['_' => [], 'items' => [], 'running' => false, 'mostRecentEvent' => false];
        $package['_']['refreshUrl'] = Url::to('/' . $this->id . '/nav-package');
        $package['_']['resolveUrl'] = Url::to('/' . $this->id . '/resolve-interaction');
        $items = DeferredAction::findMine()->andWhere(['and', '`status` != "cleared"', ['or', '`expires` IS NULL', '`expires` > NOW()']])->all();
        $package['items'] = [];
        foreach ($items as $item) {
            if (!empty($item->ended)) {
                $modified = strtotime($item->modified);
                if (!$package['mostRecentEvent'] || $modified > $package['mostRecentEvent']) {
                    $package['mostRecentEvent'] = $modified;
                }
            }
            if (in_array($item->status, ['running', 'queued'])) {
                $package['running'] = true;
            }
            $package['items'][$item->primaryKey] = $item->package();
        }

        return $package;
    }
}
