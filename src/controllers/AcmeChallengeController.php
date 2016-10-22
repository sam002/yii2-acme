<?php
/**
 * Author: Semen Dubina
 * Date: 22.10.16
 * Time: 20:22
 */

namespace sam002\acme\controllers;

use yii\base\Controller;

class AcmeChallengeController extends Controller
{
    public function runAction($route, $params = [])
    {
        $challenge = $this->module->getChallengeStorage();
        return $challenge->get(basename($route));
    }
}