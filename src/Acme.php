<?php
/**
 * Author: Semen Dubina
 * Date: 16.04.16
 * Time: 23:29
 */

namespace sam002\acme;


use Amp\CoroutineResult;
use Amp\File\FilesystemException;
use function Amp\run;
use Kelunik\Acme\AcmeClient;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\OpenSSLKeyGenerator;
use Kelunik\Acme\Registration;
use sam002\acme\storages\KeyStorageFile;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\Module;
use yii\helpers\FileHelper;
use yii\validators\EmailValidator;
use yii\validators\UrlValidator;

/**
 * Class Acme is a single otp module with initialization and code-validation
 *
 * Example application configuration:
 *
 * ~~~
 *  'module' => [
 *      'acme' => [
 *          'class' => 'sam002\otp\Otp',
 *          'providerUrl' => Acme::PROVIDERS['letsencrypt:production']
 *          'keyLength' => 2048,
 *          'location' => './certs/',
 *          '$storage' => 'sam002\acme\storage\KeyStorageFile',
 *     ]
 *     ...
 * ]
 * ~~~
 *
 * @author Semen Dubina <sam@sam002.net>
 * @package sam002\otp
 */
class Acme extends Module
{
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
    public $storage = 'sam002\acme\storages\KeyStorageFile';

    /**
     * @var AcmeClient
     */
    private $client = null;

    /**
     * @var KeyStorageFile
     */
    private $keyStore = null;

    public function init()
    {
        parent::init();

        $this->checkProviderUrl();
        $this->checkStore();

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
        if (!in_array('sam002\acme\storages\KeyStorageInterface', class_implements($this->storage))) {
            throw new InvalidConfigException('Storage class "' . $this->storage . '" not implements KeyStorageInterface');
        }
    }

    /**
     * @return KeyStorageFile
     */
    private function getKeyStore()
    {
        if (empty($this->keyStore)) {
            $this->keyStore = new $this->storage(FileHelper::normalizePath($this->location));
        }
        return $this->keyStore;
    }

    /**
     * @param $email
     * @return Registration
     * @throws \Throwable
     */
    public function setup($email)
    {
        return \Amp\wait(\Amp\resolve($this->doSetup($email)));
    }

    /**
     * @param $email
     * @return \Generator
     */
    private function doSetup($email)
    {
        //check email
        $validator = new EmailValidator();
        $validator->checkDNS = true;
        if (!$validator->validate($email)) {
            throw new InvalidParamException($validator->message);
        }

        $keyFile = self::serverToKeyName($this->providerUrl . '_' . $email);

        try {
            $keyPair =$this->getKeyStore()->get($keyFile);
        } catch (FilesystemException $e) {
            $keyPair = (new OpenSSLKeyGenerator)->generate($this->keyLength);
            $keyPair = $this->getKeyStore()->put($keyFile, $keyPair);
        }
        $acme = new AcmeService(new AcmeClient($this->providerUrl, $keyPair));

        /** @var Registration $registration */
        $registration = (yield $acme->register($email));

        yield new CoroutineResult($registration);
    }


    public function issue($domains = [],$name = '')
    {
        return \Amp\wait($this->doIssue($domains, $name));
    }

    private function doIssue()
    {
        yield new CoroutineResult(0);
    }

    /**
     * Transforms a directory URI to a valid filename for usage as key file name.
     *
     * @param string $server URI to the directory
     * @return string identifier usable as file name
     */
    static function serverToKeyName($server)
    {
        $server = substr($server, strpos($server, "://") + 3);
        $keyFile = str_replace("/", ".", $server);
        $keyFile = preg_replace("@[^a-z0-9._-]@", "", $keyFile);
        $keyFile = preg_replace("@\\.+@", ".", $keyFile);
        return $keyFile;
    }

}