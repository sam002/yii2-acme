<?php
/**
 * Author: Semen Dubina
 * Date: 24.04.16
 * Time: 21:03
 */

namespace sam002\acme\storages\file;


use Amp\File\FilesystemException;
use sam002\acme\storages\ChallengeStorageInterface;
use yii\base\InvalidParamException;

class ChallengeStorageFile  extends FileStorage implements ChallengeStorageInterface
{
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
        return $this->getRoot() . DIRECTORY_SEPARATOR . 'challenge' . DIRECTORY_SEPARATOR . "{$name}";
    }
}