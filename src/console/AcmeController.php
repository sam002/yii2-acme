<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 3:07
 */

namespace sam002\acme\console;


use function Amp\run;
use sam002\acme\Acme;
use Yii;
use yii\base\Exception;
use yii\console\Controller;
use yii\helpers\Console;
use yii\validators\EmailValidator;
use yii\validators\UrlValidator;

/**
 * Manage ssl certificates using ACME protocol
 * @default quick
 * @package sam002\acme\console
 */
class AcmeController extends Controller
{

    /**
     * @var string controller default action ID.
     */
    public $defaultAction = 'quick';
    /**
     * @var string required, root directory of all source files.
     */
    public $sourcePath = '@yii';

    /**
     * Quick setup, issue/renew certificates and print info.
     * For disabling interactive mode set first arguments as valid email and second argument as 'true'
     * @param string $email
     * @param bool $force
     * @return int
     */
    public function actionQuick($email = '', $force = false)
    {

        $validator = new EmailValidator();
        if (!$validator->validate($email)) {
            if ($force) {
                $this->stdout($validator->message);
                return Controller::EXIT_CODE_ERROR;
            }
            $email = $this->prompt('Email contact:', [
                'validator' => function ($data) use ($validator) {
                    return $validator->validate($data);
                }
            ]);
        }
        $this->actionSetup($email, $force);
        //todo get list and statuses
        //todo renew old, issue for base url;
    }


    /**
     * Setup account by email
     * @param string $email
     * @param bool $force
     * @return int
     */
    public function actionSetup($email, $force = false)
    {
        if (!isset(\Yii::$app->acme)) {
            if(!$force && $this->confirm("yii2-acme has default configuration. Advanced setup?", false)) {
                $config = $this->advanced();
            } else {
                $this->stdout(sprintf("Setup for %s provider\n",  Acme::PROVIDERS['letsencrypt:production']));
                $config = [
                    'providerUrl' => Acme::PROVIDERS['letsencrypt:production']
                ];
            }
            $acme = new Acme('acme', null, $config);
        } else {
            $acme = \Yii::$app->acme;
        }
        try {
            $register = $acme->setup($email);
            $this->stdout("You success registered.\n");
            $this->stdout(sprintf("Agreements: %s\n", $register->getAgreement()));
        } catch (Exception $e) {
            $this->stderr("Something went wrong", Console::BOLD|Console::BG_RED);
            $this->stderr($e->getMessage(), Console::BOLD);
            $this->stderr($e->getTraceAsString(), Console::ITALIC);
        }
        return Controller::EXIT_CODE_NORMAL;
    }

    public function actionIssue(array $domains = [], $name = '') {
        //todo Issure certificate
        //todo print path and instruction link
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

    private function advanced()
    {
        $providerSelect = $this->select('Choose provider:', array_merge(Acme::PROVIDERS, ['custom'=>'interactive input a custom uri']));
        if ($providerSelect === 'custom') {
            $provider = $this->prompt('Set ACME provider uri, directory path need', [
                'validator' => function($data) {
                    $validator = new UrlValidator();
                    $validator->validSchemes = ['https'];
                    return $validator->validate($data);
                }
            ]);
        } else {
            $provider = Acme::PROVIDERS[$providerSelect];
        }
        return [
            'providerUrl' => $provider,
            'keyLength' => (int)$this->prompt('Set key length (2048 minimum and recommended):', [
                'default'   => 2048,
                'pattern'   => '/^(2(0(4[8,9]|[5-9][0-9])|[1-9][0-9]{2})|[3-9][0-9]{4})$/',
            ]),
            'location' => $this->prompt("Set location path:", [
                'default'   => Yii::getAlias($this->sourcePath . '/acme')
            ]),
        ];
    }
}