<?php
/**
 * Author: Semen Dubina
 * Date: 24.04.16
 * Time: 21:00
 */

namespace sam002\acme\storages;


interface ChallengeStorageInterface
{

    /**
     * @param string $token
     * @return string
     */
    public function get($token = '');

    /**
     * @param string $token
     * @param string $payload
     */
    public function put($token = '', $payload = '');

    /**
     * @param string $token
     * @return boolean
     */
    public function delete($token = '');

}
