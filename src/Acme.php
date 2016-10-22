<?php
/**
 * Author: Semen Dubina
 * Date: 16.04.16
 * Time: 23:29
 */

namespace sam002\acme;

use function Amp\run;
use Kelunik\Acme\AcmeClient;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\KeyPair;
use sam002\acme\resources\Info;
use sam002\acme\resources\Issue;
use sam002\acme\resources\Setup;
use sam002\acme\storages\file\CertificateStorageFile;
use sam002\acme\storages\CertificateStorageInterface;
use sam002\acme\storages\ChallengeStorageInterface;
use sam002\acme\storages\file\KeyStorageFile;
use sam002\acme\storages\file\ChallengeStorageFile;
use sam002\acme\storages\KeyStorageInterface;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\helpers\FileHelper;
use yii\validators\UrlValidator;

/**
 * Class Acme for certificate management using ACME (Automatic Certificate Management Environment) protocol
 *
 * Example application configuration:
 *
 * ~~~
 *  'module' => [
 *      'acme' => [
 *          'class' => 'sam002\acme\Acme',
 *          'providerUrl' => Acme::PROVIDERS['letsencrypt:production']
 *          'keyLength' => 2048,
 *          'location' => './certs/',
 *          'keyStorage' => 'sam002\acme\storage\KeyStorageFile',
 *          'certificateStorage' => 'sam002\acme\storage\CertificateStorageFile'
 *          'challengeStorage' => 'sam002\acme\storage\ChallengeStorageFile'
 *     ]
 *     ...
 * ]
 * ~~~
 *
 * @author Semen Dubina <sam@sam002.net>
 * @package sam002\acme
 */
class Acme extends Module
{
    use Setup, Issue, Info;

    const PROVIDERS = [
            'letsencrypt:production' => 'https://acme-v01.api.letsencrypt.org/directory',
            'letsencrypt:staging' => 'https://acme-staging.api.letsencrypt.org/directory',
        ];

    /**
     * @var string
     */
    public $providerUrl = 'https://acme-staging.api.letsencrypt.org/directory';

    /**
     * @var int
     */
    public $keyLength = 2048;

    /**
     * @var string
     */
    public $location = '';

    /**
     * @var string
     */
    public $keyStorage = 'sam002\acme\storages\file\KeyStorageFile';

    /**
     * @var string
     */
    public $certificateStorage = 'sam002\acme\storages\file\CertificateStorageFile';

    /**
     * @var string
     */
    public $challengeStorage = 'sam002\acme\storages\file\ChallengeStorageFile';

    /** @var KeyStorageInterface */
    private $keyStore = null;

    /** @var CertificateStorageInterface */
    private $certificateStore = null;

    /** @var ChallengeStorageInterface */
    private $challengeStore = null;

    public function init()
    {
        parent::init();

        $this->checkProviderUrl();
        $this->checkStore();
        //Add
        $this->controllerMap = [
            'cert' => 'sam002\acme\console\AcmeController',
            'acme-challenge' => function()
            {
                $challenge = $this->getChallengeStorage();
                echo $challenge->get(basename(\Yii::$app->request->getPathInfo()));
                \Yii::$app->response->send();
                die();
            }
        ];

    }

    /**
     * @throws InvalidConfigException
     */
    private function checkProviderUrl()
    {

        $validator = new UrlValidator();
        if (!$validator->validate($this->providerUrl)) {
            throw new InvalidConfigException($validator->message);
        }
        unset($validator);
    }

    private function checkStore()
    {
        if (!in_array('sam002\acme\storages\KeyStorageInterface', class_implements($this->keyStorage))) {
            throw new InvalidConfigException('keyStorage class "' . $this->keyStorage . '" not implements KeyStorageInterface');
        }

        if (!in_array('sam002\acme\storages\CertificateStorageInterface', class_implements($this->certificateStorage))) {
            throw new InvalidConfigException('CertificateStorage class "' . $this->certificateStorage . '" not implements CertificateStorageInterface');
        }
    }

    /**
     * @return string
     */
    public function getProviderUrl()
    {
        return $this->providerUrl;
    }

    /**
     * @param string $providerUrl
     */
    public function setProviderUrl($providerUrl)
    {
        $this->providerUrl = $providerUrl;
    }

    /**
     * @return KeyStorageFile
     */
    protected function getKeyStorage()
    {
        if (empty($this->keyStore)) {
            $this->keyStore = new $this->keyStorage(FileHelper::normalizePath($this->location));
        }
        return $this->keyStore;
    }

    /**
     * @return CertificateStorageFile
     */
    protected function getCertificateStorage()
    {
        if (empty($this->certificateStore)) {
            $this->certificateStore = new $this->certificateStorage(FileHelper::normalizePath($this->location));
        }
        return $this->certificateStore;
    }

    /**
     * @return ChallengeStorageFile
     */
    protected function getChallengeStorage()
    {
        if (empty($this->challengeStore)) {
            $this->challengeStore = new $this->challengeStorage(FileHelper::normalizePath($this->location));
        }
        return $this->challengeStore;
    }

    /**
     * @param KeyPair $keyPair
     * @return AcmeService
     */
    protected function getAcmeService(KeyPair $keyPair)
    {
        return new AcmeService(new AcmeClient($this->providerUrl, $keyPair));
    }


    /**
     * Transforms a directory URI to a valid filename for usage as key file name.
     * @param string $server URI to the directory
     * @return string identifier usable as file name
     */
    protected function serverToKeyName($server = '')
    {
        if (empty($server)) {
            $server = $this->getProviderUrl();
        }
        $server = substr($server, strpos($server, "://") + 3);
        $keyFile = str_replace("/", ".", $server);
        $keyFile = preg_replace("@[^a-z0-9._-]@", "", $keyFile);
        $keyFile = preg_replace("@\\.+@", ".", $keyFile);
        return $keyFile;
    }

}