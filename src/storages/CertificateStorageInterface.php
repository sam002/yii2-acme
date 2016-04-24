<?php
/**
 * Author: Semen Dubina
 * Date: 17.04.16
 * Time: 0:28
 */

namespace sam002\acme\storages;

use Kelunik\Certificate\Certificate;

interface CertificateStorageInterface
{
    /**
     * @param string $name
     * @return string
     */
    public function get($name = '');

    /**
     * @param Certificate $certificate
     * @return boolean
     */
    public function put(Certificate $certificate);

    /**
     * @param string $name
     * @return boolean
     */
    public function delete($name = '');
}