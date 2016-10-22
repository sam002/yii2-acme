<?php
/**
 * Author: Semen Dubina
 * Date: 24.04.16
 * Time: 16:58
 */

namespace sam002\acme\resources;

use Amp\CoroutineResult;
use Amp\Dns\Record;
use Amp\File\FilesystemException;
use Kelunik\Acme\AcmeException;
use Kelunik\Acme\AcmeService;
use Kelunik\Acme\KeyPair;
use Kelunik\Acme\OpenSSLKeyGenerator;
use sam002\acme\storages\file\CertificateStorageFile;
use sam002\acme\storages\file\ChallengeStorageFile;
use sam002\acme\storages\KeyStorageInterface;
use yii\base\InvalidCallException;

trait Issue
{
    /**
     * @param KeyPair $keyPair
     * @return AcmeService
     */
    abstract protected function getAcmeService(KeyPair $keyPair);

    /**
     * @return KeyStorageInterface
     */
    abstract protected function getKeyStorage();


    /**
     * @return CertificateStorageFile
     */
    abstract protected function getCertificateStorage();

    /**
     * @return ChallengeStorageFile
     */
    abstract protected function getChallengeStorage();

    /**
     * @param $provider
     * @return mixed
     */
    abstract protected function serverToKeyName($provider = '');

    /**
     * @param array $domains
     * @return mixed
     * @throws \Throwable
     */
    public function issue($domains = [])
    {
        return \Amp\wait(\Amp\resolve($this->doIssue($domains)));
    }

    /**
     * @param $domains
     * @return \Generator
     * @throws AcmeException
     */
    private function doIssue($domains)
    {
        //validate domains
        yield \Amp\resolve($this->checkDnsRecords($domains));

        //todo check avalibles aliases an applications and find each roots
//        $docRoots = explode(PATH_SEPARATOR, str_replace("\\", "/", $root));

        //todo find account key
        $keyFile = $this->serverToKeyName();

        try {
            $keyPair =$this->getKeyStorage()->get($keyFile);
        } catch (FilesystemException $e) {
            throw new InvalidCallException("Account key not found, did you run 'yii acme/setup' or 'yii acme/quick'?", 0, $e);
        }
        $acme = $this->getAcmeService($keyPair);

        $promises = [];
        foreach ($domains as $i => $domain) {
            $promises[] = \Amp\resolve($this->solveChallenge($acme, $keyPair, $domain));
        }
        list($errors) = (yield \Amp\any($promises));
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo $error->getMessage() . PHP_EOL;
            }
            throw new AcmeException("Issuance failed, not all challenges could be solved.");
        }

        $path = implode(DIRECTORY_SEPARATOR, ['certs', $this->serverToKeyName(), reset($domains)]);
        $keyPath = $path . DIRECTORY_SEPARATOR . 'key';
        try {
            $keyPair = $this->getKeyStorage()->get($keyPath);
        } catch (FilesystemException $e) {
            $keyPair = (new OpenSSLKeyGenerator)->generate($this->keyLength);
            $keyPair = $this->getKeyStorage()->put($keyPath, $keyPair);
        }

        $location = (yield $acme->requestCertificate($keyPair, $domains));
        $certificates = (yield $acme->pollForCertificate($location));

        $certificateStore = $this->getCertificateStorage();
        $certificateStore->setRoot($certificateStore->getRoot() . $path);
        $result = $certificateStore->put($certificates);
        yield new CoroutineResult($result);
    }

    /**
     * @param $domains
     * @throws AcmeException
     */
    private function checkDnsRecords($domains) {
        $promises = [];
        foreach ($domains as $domain) {
            $promises[$domain] = \Amp\Dns\resolve($domain, [
                "types" => [Record::A],
                "hosts" => false,
            ]);
        }
        list($errors) = (yield \Amp\any($promises));
        if (!empty($errors)) {
            throw new AcmeException("Couldn't resolve the following domains to an IPv4 record: " . implode(", ", array_keys($errors)));
        }
    }

    /**
     * @param AcmeService $acme
     * @param KeyPair $keyPair
     * @param $domain
     * @return \Generator
     * @throws AcmeException
     * @throws \Exception
     * @throws \Throwable
     */
    private function solveChallenge(AcmeService $acme, KeyPair $keyPair, $domain) {
        list($location, $challenges) = (yield $acme->requestChallenges($domain));
        $goodChallenges = $this->findSuitableCombination($challenges);
        if (empty($goodChallenges)) {
            throw new AcmeException("Couldn't find any combination of challenges which this client can solve!");
        }
        $challenge = $challenges->challenges[reset($goodChallenges)];
        $token = $challenge->token;
        if (!preg_match("#^[a-zA-Z0-9-_]+$#", $token)) {
            throw new AcmeException("Protocol violation: Invalid Token!");
        }
        $payload = $acme->generateHttp01Payload($keyPair, $token);
        $challengeStore = $this->getChallengeStorage();
        try {
            $challengeStore->put($token, $payload);
            yield $acme->verifyHttp01Challenge($domain, $token, $payload);
            yield $acme->answerChallenge($challenge->uri, $payload);
            yield $acme->pollForChallenge($location);
            $challengeStore->delete($token);
        } catch (\Exception $e) {
            // no finally because generators...
            $challengeStore->delete($token);
            throw $e;
        } catch (\Throwable $e) {
            // no finally because generators...
            $challengeStore->delete($token);
            throw $e;
        }
    }

    /**
     * @param \stdClass $response
     * @return array
     */
    private function findSuitableCombination(\stdClass $response) {
        $challenges = isset($response->challenges) ? $response->challenges : [];
        $combinations = isset($response->combinations) ? $response->combinations : [];
        $goodChallenges = [];
        foreach ($challenges as $i => $challenge) {
            if ($challenge->type === "http-01") {
                $goodChallenges[] = $i;
            }
        }
        foreach ($goodChallenges as $i => $challenge) {
            if (!in_array([$challenge], $combinations)) {
                unset($goodChallenges[$i]);
            }
        }
        return $goodChallenges;
    }
}
