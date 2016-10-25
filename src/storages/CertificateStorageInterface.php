<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 0:28
 */

namespace sam002\acme\storages;

interface CertificateStorageInterface
{
    /**
     * @param string $name
     * @return string
     */
    public function get($name = '');

    /**
     * @param array $certificates
     * @return boolean
     */
    public function put($certificates = []);

    /**
     * @param string $name
     * @return boolean
     */
    public function delete($name = '');
}
