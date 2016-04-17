<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 2:24
 */

namespace sam002\acme\storage;

use Amp\File\FilesystemException;
use Kelunik\Acme\KeyPair;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\validators\FileValidator;

class KeyStorageFile implements KeyStorageInterface
{
    /**
     * @param string $name
     * @return KeyPair
     */
    public function get($name = '')
    {
        $file = $this->getFileName($name);
        $realPath = realpath($file);
        if (!$realPath) {
            throw new InvalidParamException("File not found: '{$file}'");
        }
        $privateKey = (yield \Amp\File\get($realPath));
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
        $validator = new FileValidator();
        if(!$validator->validate($validator)) {
            throw new InvalidParamException($validator->message);
        }

        $file = $this->getFileName($name);
        try {
            // TODO: Replace with async version once available
            if (!file_exists(dirname($file))) {
                mkdir(dirname($file), 0755, true);
            }
            yield \Amp\File\put($file, $keyPair->getPrivate());
            yield \Amp\File\chmod($file, 0600);
        } catch (FilesystemException $e) {
            throw new Exception("Could not save key.", 0, $e);
        }
        return $keyPair;
    }

    private function getFileName($name)
    {
        $path = \Yii::$app->runtimePath . "/acme/accounts/{$name}.pem";
        return $path;
    }
}