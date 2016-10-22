<?php
/**
 * Author: Semen Dubina
 * Date: 24.04.16
 * Time: 16:58
 */

namespace sam002\acme\resources;

use Kelunik\Acme\AcmeService;
use Kelunik\Acme\KeyPair;
use sam002\acme\storages\KeyStorageInterface;
use yii\validators\EmailValidator;
use yii\base\InvalidParamException;
use Kelunik\Acme\Registration;
use Amp\File\FilesystemException;
use Kelunik\Acme\OpenSSLKeyGenerator;
use Amp\CoroutineResult;

trait Setup
{

    /**
     * @param KeyPair $keyPair
     * @return AcmeService
     */
    abstract protected function getAcmeService(KeyPair $keyPair);

    /**
     * @return KeyStorageInterface
     */
    abstract protected function getKeyStorage();

    /**
     * @param $provider
     * @return mixed
     */
    abstract protected function serverToKeyName($provider = '');

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
     * @throws InvalidParamException
     */
    private function doSetup($email)
    {
        //check email
        $validator = new EmailValidator();
        $validator->checkDNS = true;
        if (!$validator->validate($email)) {
            throw new InvalidParamException($validator->message);
        }

        $keyFile =  $this->serverToKeyName();

        try {
            $keyPair = $this->getKeyStorage()->get($keyFile);
        } catch (FilesystemException $e) {
            $keyPair = (new OpenSSLKeyGenerator)->generate($this->keyLength);
            $keyPair = $this->getKeyStorage()->put($keyFile, $keyPair);
        }
        $acme = $this->getAcmeService($keyPair);

        /** @var Registration $registration */
        $registration = (yield $acme->register($email));

        yield new CoroutineResult($registration);
    }
}
