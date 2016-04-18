<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 2:24
 */

namespace sam002\acme\storages;

use Amp\File\FilesystemException;
use Kelunik\Acme\KeyPair;
use yii\base\Exception;
use yii\helpers\FileHelper;

class KeyStorageFile implements KeyStorageInterface
{

    /**
     * @var string
     */
    public $root = '';

    /**
     * @param string $name
     * @return KeyPair
     */
    public function get($name = '')
    {
        $file = $this->getFileName($name);
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
                FileHelper::createDirectory(dirname($file), 0755, true);
            }
            file_put_contents($file, $keyPair->getPrivate(), LOCK_EX);
            chmod($file, 0600);
        } catch (FilesystemException $e) {
            throw new Exception("Could not save key.", 0, $e);
        }
        return $keyPair;
    }

    private function getFileName($name)
    {
        if (empty($this->root)) {
            $this->root = \Yii::$app->runtimePath . '/acme/';
        }
        $path = realpath($this->root . "{$name}.pem");
        return $path;
    }
}