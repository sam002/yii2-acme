<?php
/**
 * Author: Semen Dubina
 * Date: 16.04.16
 * Time: 23:29
 */

namespace sam002\acme;


use Amp\File\FilesystemException;
use Kelunik\Acme\AcmeClient;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\OpenSSLKeyGenerator;
use Kelunik\Acme\Registration;
use sam002\acme\storage\KeyStorageFile;
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
    public $keyLength = 4096;

    /**
     * @var string
     */
    public $location = '';

    /**
     * @var string
     */
    public $storage = 'sam002\acme\storage\KeyStorageFile';

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
     * @param string $url
     * @throws InvalidConfigException
     */
    private function checkProviderUrl($url = '')
    {

        $validator = new UrlValidator();
        if (!$validator->validate($this->providerUrl)) {
            throw new InvalidConfigException($validator->message);
        }
        unset($validator);
    }

    private function checkStore()
    {
        //todo check implementation and string as callback
        if (!in_array('KeyStorageInterface', class_implements($this->storage))) {
            throw new InvalidParamException('Storage class "' . $this->storage . '" not implements KeyStorageInterface');
        }
    }

    private function initStore()
    {
        if (empty($this->keyStore)) {
            $this->keyStore = new $this->storage(FileHelper::normalizePath($this->location));
        }
        return $this->keyStore;
    }

    /**
     * @param $email
     * @return Registration
     */
    public function register($email)
    {
        //check email
        $validator = new EmailValidator();
        $validator->checkDNS = true;
        if (!$validator->validate($email)) {
            throw new InvalidParamException($validator->message);
        }

        $keyFile = self::serverToKeyName($this->providerUrl);
        $path = "accounts/{$keyFile}.pem";

        try {
            $keyPair = (yield $this->keyStore->get($path));
        } catch (FilesystemException $e) {
            $keyPair = (new OpenSSLKeyGenerator)->generate($this->keyLength);
            $keyPair = (yield $this->keyStore->put($path, $keyPair));
        }
        $acme = new AcmeService(new AcmeClient($this->providerUrl, $keyPair));

        return $acme->register($email);
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