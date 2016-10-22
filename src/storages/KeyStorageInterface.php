<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 0:28
 */

namespace sam002\acme\storages;


use Kelunik\Acme\KeyPair;

interface KeyStorageInterface
{
    /**
     * @param string $name
     */
    public function get($name = '');

    /**
     * @param string $name
     * @param KeyPair $keyPair
     */
    public function put($name = '', KeyPair $keyPair);

}
