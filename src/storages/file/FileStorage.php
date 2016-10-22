<?php
/**
 * Author: Semen Dubina
 * Date: 29.05.16
 * Time: 17:55
 */

namespace sam002\acme\storages\file;


use Amp\File\FilesystemException;
use yii\helpers\FileHelper;

/**
 * Class FileStorage
 * @package sam002\acme\storages\file
 */
class FileStorage
{

    /**
     * @var string
     */
    public $root = '';

    public function __construct($path)
    {
        $this->setRoot($path);
    }

    public function checkDir()
    {
        if (!FileHelper::createDirectory($this->root, 0755)) {
            throw new FilesystemException("Couldn't create root acme directory: '{$this->root}'");
        };
    }

    /**
     * @return null
     */
    public function getRoot()
    {
        if (empty($this->root)) {
            $this->root = \Yii::$app->runtimePath . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR;
        }
        return $this->root;
    }

    /**
     * @param null $root
     */
    public function setRoot($root)
    {
        $this->root = $root;
        if (empty($this->root)) {
            $this->root = \Yii::$app->runtimePath . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR;
        }
        $this->checkDir();
    }
}
