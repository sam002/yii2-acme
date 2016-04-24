<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 0:28
 */

namespace sam002\acme\storages;


use Amp\File\FilesystemException;
use Kelunik\Certificate\Certificate;
use yii\base\InvalidParamException;

class CertificateStorageFile implements CertificateStorageInterface
{
    const FILE_CERT = "cert.pem";
    const FILE_FULLCHAIN = "fullchain.pem";
    const FILE_CHAIN = "chain.pem";

    public $root = "";

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
     * @param Certificate $certificate
     * @return boolean
     * @throws InvalidParamException
     */
    public function put(Certificate $certificate)
    {
        $cert = new Certificate($certificate);
        $commonName = $cert->getSubject()->getCommonName();

        if (!$commonName) {
            throw new InvalidParamException("Certificate doesn't have a common name.");
        }
        // See https://github.com/amphp/dns/blob/4c4d450d4af26fc55dc56dcf45ec7977373a38bf/lib/functions.php#L83
        if (isset($commonName[253]) || !preg_match("~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9]){0,1})(?:\\.[a-z0-9][a-z0-9-]{0,61}[a-z0-9])*$~i", $commonName)) {
            throw new InvalidParamException("Invalid common name: '{$commonName}'");
        }

        $path = $this->getRoot($commonName);
        $realpath = realpath($path);
        if (!$realpath && !mkdir($path, 0775, true)) {
            throw new FilesystemException("Couldn't create certificate directory: '{$path}'");
        }
        file_put_contents($path . self::FILE_CERT, $certificate);
        $result = chmod($path . self::FILE_CERT, 0644);
        file_put_contents($path . self::FILE_FULLCHAIN, implode(PHP_EOL, array_merge($chain)));
        $result &= chmod($path . self::FILE_FULLCHAIN, 0644);
        file_put_contents($path . self::FILE_CHAIN, implode(PHP_EOL, $chain));
        $result &= chmod($path . self::FILE_CHAIN, 0644);
        return $result;
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function delete($name = '')
    {
        foreach (scandir($this->getRoot($name)) as $file) {
            unlink($this->getRoot($name) . DIRECTORY_SEPARATOR . $file);
        }
        return rmdir($this->getRoot($name));
    }

    private function getRoot($name)
    {
        if (empty($this->root)) {
            $this->root = \Yii::$app->runtimePath . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR ;
        }
        return $this->root . "{$name}". DIRECTORY_SEPARATOR ;
    }
}