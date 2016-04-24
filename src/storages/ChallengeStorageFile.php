<?php
/**
 * Author: Semen Dubina
 * Date: 24.04.16
 * Time: 21:03
 */

namespace sam002\acme\storages;


use Amp\File\FilesystemException;
use yii\base\InvalidParamException;

class ChallengeStorageFile implements ChallengeStorageInterface
{

    private $root = '';

    /**
     * @param string $token
     * @return string
     */
    public function get($token = '')
    {
        return file_get_contents($this->getFileName($token));
    }

    /**
     * @param string $token
     * @param string $payload
     * @return boolean
     */
    public function put($token = '', $payload = '')
    {
        $path = $this->getFileName($token);
        $realpath = realpath(dirname($path));
        if (!$realpath && !mkdir( dirname($path), 0775, true)) {
            throw new FilesystemException("Couldn't create certificate directory: '{$path}'");
        }
        return false !== file_put_contents($this->getFileName($token), $payload);
    }

    /**
     * @param string $token
     * @return boolean
     */
    public function delete($token = '')
    {
        return unlink($this->getFileName($token));
    }

    private function getFileName($name)
    {
        if (empty($this->root)) {
            $this->root = \Yii::$app->runtimePath . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'challenge' . DIRECTORY_SEPARATOR ;
        }
        return $this->root . "{$name}";
    }
}