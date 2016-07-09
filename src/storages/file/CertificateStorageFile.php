<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 0:28
 */

namespace sam002\acme\storages\file;


use Amp\File\FilesystemException;
use Kelunik\Certificate\Certificate;
use sam002\acme\storages\CertificateStorageInterface;
use yii\base\InvalidParamException;

class CertificateStorageFile  extends FileStorage implements CertificateStorageInterface
{
    const FILE_CERT = "cert.pem";
    const FILE_FULLCHAIN = "fullchain.pem";
    const FILE_CHAIN = "chain.pem";

    /**
     * @param string $name
     * @return string
     */
    public function get($name = '')
    {
        if (empty($this->root)) {
            $this->root = \Yii::$app->runtimePath . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR;
        }
        return file_get_contents($this->root . $name . DIRECTORY_SEPARATOR . self::FILE_CERT);
    }

    /**
     * @param array $certificates
     * @return boolean
     * @throws InvalidParamException
     */
    public function put($certificates = [])
    {
        $cert = new Certificate($certificates[0]);
        $commonName = $cert->getSubject()->getCommonName();

        if (!$commonName) {
            throw new InvalidParamException("Certificate doesn't have a common name.");
        }
        // See https://github.com/amphp/dns/blob/4c4d450d4af26fc55dc56dcf45ec7977373a38bf/lib/functions.php#L83
        if (isset($commonName[253]) || !preg_match("~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9]){0,1})(?:\\.[a-z0-9][a-z0-9-]{0,61}[a-z0-9])*$~i", $commonName)) {
            throw new InvalidParamException("Invalid common name: '{$commonName}'");
        }

        $chain = array_slice($certificates, 1);

        $path = $this->getFileName("/");
        $realpath = realpath(dirname($path));
        if (!$realpath && !mkdir( dirname($path), 0775, true)) {
            throw new FilesystemException("Couldn't create directory: '{$path}'");
        }
        file_put_contents($this->getFileName(self::FILE_CERT), $certificates);
        $result = chmod($this->getFileName(self::FILE_CERT), 0644);
        file_put_contents($this->getFileName(self::FILE_FULLCHAIN), implode(PHP_EOL, array_merge($chain)));
        $result &= chmod($this->getFileName(self::FILE_FULLCHAIN), 0644);
        file_put_contents($this->getFileName(self::FILE_CHAIN), implode(PHP_EOL, $chain));
        $result &= chmod($this->getFileName(self::FILE_CHAIN), 0644);
        return $result;
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function delete($name = '')
    {
        foreach (scandir($this->getFileName($name)) as $file) {
            unlink($this->getFileName($name) . DIRECTORY_SEPARATOR . $file);
        }
        return rmdir($this->getFileName($name));
    }

    /**
     * @param string $name
     * @return string
     */
    private function getFileName($name = "")
    {
        return $this->getRoot() . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . "{$name}";
    }
}