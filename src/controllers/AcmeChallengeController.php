<?php
/**
 * Author: Semen Dubina
 * Date: 22.10.16
 * Time: 20:22
 */

namespace sam002\acme\controllers;

use yii\base\Controller;
use yii\web\ForbiddenHttpException;

class AcmeChallengeController extends Controller
{

    public function runAction($route, $params = [])
    {
        if (!empty($params)) {
            throw new ForbiddenHttpException(\Yii::t('yii', 'You are not allowed to perform this action.'));
        }
        $challenge = $this->module->getChallengeStorage();
        return $challenge->get(basename($route));
    }
}
