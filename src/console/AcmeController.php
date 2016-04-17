<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 3:07
 */

namespace sam002\acme\console;


use yii\console\Controller;

class AcmeController extends Controller
{
    public function actionSetup($email = '') {
        //todo setup
        return Controller::EXIT_CODE_NORMAL;
    }

    public function actionIssue(array $domains = [], $name = '') {
        //todo Issure certificate
        //print path and instruction link
        return Controller::EXIT_CODE_NORMAL;
    }

    public function actionRevoke($name = '') {
        //todo revoke
        return Controller::EXIT_CODE_NORMAL;
    }

    public function actionRenew($name = '') {
        //todo renew certificate
        return Controller::EXIT_CODE_NORMAL;
    }

    public function actionInfo($name = '') {
        //todo list certificates and setup state
        return Controller::EXIT_CODE_NORMAL;
    }
}