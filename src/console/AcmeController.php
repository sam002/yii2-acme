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
use yii\base\InvalidParamException;
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
     * @return int
     */
    public function actionQuick($email = '')
    {
        if (!$this->actionSetup($email)) {
            return Controller::EXIT_CODE_ERROR;
        }
        $this->actionIssue([]);
        //todo renew old, issue for base url;
        //todo get list and statuses
    }


    /**
     * Setup account by email
     * @param string $email
     * @return int
     */
    public function actionSetup($email)
    {
        $email = $this->validateEmail($email);
        $acme = $this->getAcme();
        try {
            $register = $acme->setup($email);
            $this->stdout("You success registered.\n");
            $this->stdout(sprintf("Please, read agreements: %s\n", $register->getAgreement()));
        } catch (Exception $e) {
            $this->stderr("Something went wrong\n", Console::BOLD|Console::FG_RED);
            $this->stderr($e->getMessage(), Console::BOLD);
            $this->stderr($e->getTraceAsString(), Console::ITALIC);
        }
        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * Issue
     * @param array $domains
     * @return int
     */
    public function actionIssue(array $domains = [])
    {
        //Manual control...
        if (count($domains) > 100) {
            $this->stderr("Maximum 100 domains per certificate", Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }

        //setup domains
        $domains = $this->interactive ? $this->domainsSet($domains) : [];

        $acme = $this->getAcme();
        try {
            $acme->issue($domains);
            $this->stdout("Certificate success registered.\n");
        } catch (Exception $e) {
            $this->stderr("Something went wrong\n", Console::BOLD|Console::FG_RED);
            $this->stderr($e->getMessage(), Console::BOLD);
            $this->stderr($e->getTraceAsString(), Console::ITALIC);
        }

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

    public function actionInfo() {
        //todo list certificates and setup state
        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * @param $email
     * @return int
     */
    private function validateEmail($email)
    {
        $validator = new EmailValidator();
        if (!$validator->validate($email)) {
            if (!$this->interactive) {
                throw new InvalidParamException($validator->message);
            }
            $message = empty($email) ? "Email is empty\n": "Email not valid\n";
            $this->stdout($message);
            $email = $this->prompt('Set email:', [
                'validator' => function ($data) use ($validator) {
                    return $validator->validate($data);
                }
            ]);
        }
        return $email;
    }

    /**
     * Init Acme extension
     * @return mixed|Acme
     */
    private function getAcme()
    {
        if (!isset(\Yii::$app->acme)) {
            if($this->interactive && $this->confirm("yii2-acme has default configuration. Advanced setup?", false)) {
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
        return $acme;
    }

    /**
     * Advanced configuration
     * @return array
     */
    private function advanced()
    {
        $providerSelect = $this->select('Choose provider:', array_merge(Acme::PROVIDERS, [
            'custom'=>'input a custom uri'
        ]));
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

    /**
     * Set domains
     * @param array $domains
     * @return array
     */
    private function domainsSet($domains = [])
    {
        if (empty($domains) || $this->confirm("Edit the list of domains?", false)) {
            //force get available domains
            $domainsSearched = array_filter(Yii::$aliases, function ($data, $key) {
                return is_string($key) && filter_var($data, FILTER_VALIDATE_URL) ;
            }, ARRAY_FILTER_USE_BOTH);
            if (empty($domains)) {
                $domains = ['manual' => 'manual set'];
            }

            //validate prompt as URL
            $urlValidation =  function ($input, &$error) use ($domains) {
                if(in_array($input, $domains)) {
                    $error = "Always set";
                    return false;
                }
                $urlValidator = new UrlValidator();
                $urlValidator->defaultScheme = 'http';
                $result = $urlValidator->validate($input, $error);
//                $error = $urlValidator->message;
                unset($urlValidator);
                return $result;
            };

            $checked = $this->select("Select main domain:", array_merge($domainsSearched, $domains));
            $domains = [];
            $domains[] = ($checked == 'manual') ? $this->prompt("Set domain:", [
                'validator' => $urlValidation
            ]) : $checked;

            while ($this->confirm("Do need to add a domain?", false)) {
                $select[] = $this->prompt("Set additional domain (type 'done' for cancel):", [
                    'validator' => $urlValidation
                ]);
            }
        }
        return $domains;
    }
}