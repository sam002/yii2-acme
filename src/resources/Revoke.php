<?php
/**
 * Author: Semen Dubina
 * Date: 24.04.16
 * Time: 16:58
 */

namespace sam002\acme\resources;

use Amp\CoroutineResult;
use Amp\File\FilesystemException;
use Kelunik\Acme\AcmeException;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\KeyPair;
use Kelunik\Certificate\Certificate;
use sam002\acme\storages\file\CertificateStorageFile;
use sam002\acme\storages\file\ChallengeStorageFile;
use sam002\acme\storages\KeyStorageInterface;
use yii\base\InvalidCallException;

trait Revoke
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
     * @return ChallengeStorageFile
     */
    abstract protected function getChallengeStorage();

    /**
     * @param $provider
     * @return mixed
     */
    abstract protected function serverToKeyName($provider = '');

    /**
     * @param string $name
     * @return mixed
     * @throws \Throwable
     */
    public function revoke($name = '')
    {
        return \Amp\wait(\Amp\resolve($this->doRevoke($name)));
    }

    /**
     * @param string $name
     * @return \Generator
     * @throws AcmeException
     */
    private function doRevoke($name = '')
    {
        $keyFile = $this->serverToKeyName();

        try {
            $keyPair =$this->getKeyStorage()->get($keyFile);
        } catch (FilesystemException $e) {
            throw new InvalidCallException("Account key not found, did you run 'yii acme/setup' or 'yii acme/quick'?", 0, $e);
        }
        $acme = $this->getAcmeService($keyPair);

        $certPath = implode(DIRECTORY_SEPARATOR, ['certs', $this->serverToKeyName(), $name]);

        try {
            /** @var Certificate $certificate */
            $certificate = $this->getCertificateStorage()->get($certPath);
        } catch (FilesystemException $e) {
            throw new InvalidCallException("There's no such certificate ({$certPath})");
        }

        yield $acme->revokeCertificate($certificate->toPem());

        $this->getCertificateStorage()->delete($certPath);

        yield new CoroutineResult(0);
    }
}
