<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 2:24
 */

namespace sam002\acme\storages\file;

use Amp\File\FilesystemException;
use Kelunik\Acme\KeyPair;
use sam002\acme\storages\KeyStorageInterface;
use yii\base\Exception;
use yii\helpers\FileHelper;

class KeyStorageFile extends FileStorage implements KeyStorageInterface
{
    /**
     * @param string $name
     * @return KeyPair
     */
    public function get($name = '')
    {
        $file = realpath($this->getFileName($name));
        if (!$file) {
            throw new FilesystemException("File not found: '{$file}'");
        }
        $privateKey = file_get_contents($file);
        $res = openssl_pkey_get_private($privateKey);
        if ($res === false) {
            throw new FilesystemException("Invalid private key: '{$file}'");
        }
        $publicKey = openssl_pkey_get_details($res)["key"];
        return new KeyPair($privateKey, $publicKey);
    }

    /**
     * @param string $name
     * @param KeyPair $keyPair
     * @return \Generator
     * @throws Exception
     */
    public function put($name = '', KeyPair $keyPair)
    {
        $file = $this->getFileName($name);
        try {
            if (!file_exists(dirname($file))) {
                FileHelper::createDirectory(dirname($file));
            }
            file_put_contents($file, $keyPair->getPrivate());
            chmod($file, 0600);
        } catch (FilesystemException $e) {
            throw new Exception("Could not save key.", 0, $e);
        }
        return $keyPair;
    }

    private function getFileName($name)
    {
        return $this->getRoot() . DIRECTORY_SEPARATOR . "{$name}.pem";
    }
}