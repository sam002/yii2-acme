<?php
/**
 * Author: Semen Dubina
 * Date: 10.07.16
 * Time: 13:07
 */

namespace sam002\acme\resources;


use Amp\CoroutineResult;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\KeyPair;
use sam002\acme\storages\file\CertificateStorageFile;
use sam002\acme\storages\KeyStorageInterface;

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

        $certificateStore = $this->getCertificateStorage();
        $domains = (yield \Amp\File\scandir($certificateStore->getRoot() . '/certs/' . $this->serverToKeyName()));
        foreach ($domains as $domain) {
            $cert = $certificateStore->get('/certs/' . $keyFile . DIRECTORY_SEPARATOR . $domain);
            $result[] = $cert;
        }

        yield new CoroutineResult($result);
    }
}
