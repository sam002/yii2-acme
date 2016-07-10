<?php
/**
 * Author: Semen Dubina
 * Date: 10.07.16
 * Time: 13:07
 */

namespace sam002\acme\resources;


use Amp\CoroutineResult;
use Amp\File\FilesystemException;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\KeyPair;
use Kelunik\Certificate\Certificate;
use sam002\acme\storages\file\CertificateStorageFile;
use sam002\acme\storages\KeyStorageInterface;
use yii\base\InvalidCallException;

trait Info
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
     * @return CertificateStorageFile
     */
    abstract protected function getCertificateStorage();

    /**
     * @param $provider
     * @return mixed
     */
    abstract protected function serverToKeyName($provider = '');

    /**
     * @return mixed
     * @throws \Throwable
     */
    public function info()
    {
        return \Amp\wait(\Amp\resolve($this->doInfo()));
    }

    public function doInfo() 
    {
        $result = [];
        $keyFile = $this->serverToKeyName();
        try {
            $keyPair = $this->getKeyStorage()->get($keyFile);
        } catch (FilesystemException $e) {
            $keyPair = [];
        }
        $result[$this->getProviderUrl()]['keys'] = $keyPair;

        $certificateStore = $this->getCertificateStorage();
        $domains = (yield \Amp\File\scandir($certificateStore->getRoot() . '/certs/' . $this->serverToKeyName()));
        foreach ($domains as $domain) {
            $pem = (yield $certificateStore->get($domain));
            $cert = new Certificate($pem);
            $result[$this->getProviderUrl()][$cert->getNames()] = $cert->getValidTo();
//                if (time() < $cert->getValidTo() && time() + $args->get("ttl") * 24 * 60 * 60 > $cert->getValidTo()) {
//                    $symbol = "<yellow> ⭮ </yellow>";
//                }
//                $this->climate->out("  [" . $symbol . "] " . implode(", ", $cert->getNames()));
        }

        yield new CoroutineResult($result);
    }
}