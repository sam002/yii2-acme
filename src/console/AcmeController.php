<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 3:07
 */

namespace sam002\acme\console;

use Kelunik\Certificate\Certificate;
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
        $this->actionRenew();
        $this->actionInfo();
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
     * Issue certificate for domains (comma separated)
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

    /**
     * Revoke certificate
     * @param string $name
     * @return int
     */
    public function actionRevoke($name = '') {
        $acme = $this->getAcme();
        $formatOutput = function(Certificate $cert) {
            $isExpired = (time() > $cert->getValidTo());
            if($isExpired) {
                return false;
            }

            $this->stdout("\n");
            $this->stdout("Certificate ", Console::BOLD);
            $this->stdout("{$cert->getSubject()->getCommonName()}\n", Console::FG_GREEN);

            $this->stdout("Domains :");
            $this->stdout(join(',', $cert->getNames()) . "\n", Console::ITALIC);

            $this->stdout("Issued by: {$cert->getIssuer()->getCommonName()}\n");
            $dateFrom = Yii::$app->formatter->asDatetime($cert->getValidFrom(), 'medium');
            $this->stdout("Valid from: {$dateFrom}\n");

            $dateTo = Yii::$app->formatter->asDatetime($cert->getValidTo(), 'medium');
            $this->stdout("Valid to: {$dateTo}\n", Console::FG_GREEN);
            return true;
        };
        try {
            $infoSrc = $acme->info();
            $certificates = [];
            foreach ($infoSrc as $key => $certInfo) {
                if($formatOutput($certInfo)) {
                    $certificates[$key + 1] = $certInfo->getSubject()->getCommonName();
                } elseif ($certInfo->getSubject()->getCommonName() === $name) {
                    $this->stdout("Certificate did already expire, no need to revoke it.\n");
                    return Controller::EXIT_CODE_NORMAL;
                }
            }

            $revokeCert = $name;

            if(empty($certificates)) {
                $this->stderr("No valid certificates\n");
                return Controller::EXIT_CODE_ERROR;
            }
            if (empty($name) || in_array($name, $certificates)) {
                $this->stdout("\n");
                $checked = $this->select("Select certificate:", $certificates);
                $revokeCert = $certificates[$checked];
            }

            $acme->revoke($revokeCert);
            $this->stdout("Certificate has been revoked\n");

        } catch (Exception $e) {
            $this->stderr("Something went wrong\n", Console::BOLD|Console::FG_RED);
            $this->stderr($e->getMessage(), Console::BOLD);
            $this->stderr($e->getTraceAsString(), Console::ITALIC);
        }
        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * Renew certificate. Argument: ttl (days left), default 1
     * @param int $ttl
     * @return int
     */
    public function actionRenew($ttl = 1) {
        $acme = $this->getAcme();
        $formatOutput = function(Certificate $cert) {
            $isExpired = (time() > $cert->getValidTo());
            $colorExpired =  !$isExpired ? Console::FG_GREEN : Console::FG_RED;

            $this->stdout("\n");
            $this->stdout("Certificate ", Console::BOLD);
            $this->stdout("{$cert->getSubject()->getCommonName()}\n", $colorExpired);

            $this->stdout("Domains :");
            $this->stdout(join(',', $cert->getNames()) . "\n", Console::ITALIC);

            $this->stdout("Issued by: {$cert->getIssuer()->getCommonName()}\n");
            $dateFrom = Yii::$app->formatter->asDatetime($cert->getValidFrom(), 'medium');
            $this->stdout("Valid from: {$dateFrom}\n");

            $dateTo = Yii::$app->formatter->asDatetime($cert->getValidTo(), 'medium');
            $this->stdout("Valid to: {$dateTo}\n", $colorExpired);
        };
        try {
            $infoSrc = $acme->info();
            /* @var  Certificate $certificate */
            foreach ($infoSrc as $certificate) {
                if (time() + $ttl*24*60*60 > $certificate->getValidTo()) {
                    $formatOutput($certificate);

                    //For if the certificate has not expired, I tell you we must revoking it.
                    if (time() < $certificate->getValidTo()) {
                        $acme->revoke($certificate->getSubject()->getCommonName());
                    }

                    $acme->issue($certificate->getNames());
                    $this->stdout("Certificate {$certificate->getSubject()->getCommonName()} success renew.\n");
                }
            }

        } catch (Exception $e) {
            $this->stderr("Something went wrong\n", Console::BOLD|Console::FG_RED);
            $this->stderr($e->getMessage(), Console::BOLD);
            $this->stderr($e->getTraceAsString(), Console::ITALIC);
        }
        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * Show info about certificates.
     * @param int $ttl
     * @return int
     */
    public function actionInfo($ttl = 7) {
        $acme = $this->getAcme();
        try {
            $formatOutput = function(Certificate $cert) use ($ttl) {
                $isExpired = (time() > $cert->getValidTo());
                $colorExpired =  !$isExpired ? Console::FG_GREEN : Console::FG_RED;

                $this->stdout("\n");
                $this->stdout("Certificate ", Console::BOLD);
                $this->stdout("{$cert->getSubject()->getCommonName()}\n", $colorExpired);

                $this->stdout("Domains :");
                $this->stdout(join(',', $cert->getNames()) . "\n", Console::ITALIC);

                $this->stdout("Issued by: {$cert->getIssuer()->getCommonName()}\n");
                $dateFrom = Yii::$app->formatter->asDatetime($cert->getValidFrom(), 'medium');
                $this->stdout("Valid from: {$dateFrom}\n");

                $dateTo = Yii::$app->formatter->asDatetime($cert->getValidTo(), 'medium');
                $this->stdout("Valid to: {$dateTo}\n", $colorExpired);

                if (!$isExpired) {
                    $colorDateDiff = (time() + $ttl*24*60*60 < $cert->getValidTo()) ? Console::FG_GREEN : Console::FG_YELLOW;
                    $dateDiff = Yii::$app->formatter->asRelativeTime($cert->getValidTo(), $cert->getValidFrom());
                    $this->stdout("Valid time left: {$dateDiff}\n", $colorDateDiff);
                }

            };
            $infoSrc = $acme->info();
            foreach ($infoSrc as $certInfo) {
                $formatOutput($certInfo);
            }
        } catch (Exception $e) {
            $this->stderr("Something went wrong\n", Console::BOLD|Console::FG_RED);
            $this->stderr($e->getMessage(), Console::BOLD);
            $this->stderr($e->getTraceAsString(), Console::ITALIC);
        }
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
        if (!\Yii::$app->has('acme')) {
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
                unset($urlValidator);
                return $result;
            };

            $checked = $this->select("Select main domain:", array_merge($domainsSearched, $domains));
            $domains = [];
            $domains[] = ($checked == 'manual') ? $this->prompt("Set domain:", [
                'validator' => $urlValidation
            ]) : $checked;

            while ($this->confirm("Do need to add a domain?", false)) {
                $domains[] = $this->prompt("Set additional domain (type 'done' for cancel):", [
                    'validator' => $urlValidation
                ]);
            }
        }
        return $domains;
    }
}
