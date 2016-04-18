<?php

abstract class DiffusionCommandEngine extends Phobject {

  private $repository;
  private $protocol;
  private $credentialPHID;
  private $argv;
  private $passthru;

  public static function newCommandEngine(PhabricatorRepository $repository) {
    $engines = self::newCommandEngines();

    foreach ($engines as $engine) {
      if ($engine->canBuildForRepository($repository)) {
        return id(clone $engine)
          ->setRepository($repository);
      }
    }

    throw new Exception(
      pht(
        'No registered command engine can build commands for this '.
        'repository ("%s").',
        $repository->getDisplayName()));
  }

  private static function newCommandEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  abstract protected function canBuildForRepository(
    PhabricatorRepository $repository);

  abstract protected function newFormattedCommand($pattern, array $argv);
  abstract protected function newCustomEnvironment();

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function setProtocol($protocol) {
    $this->protocol = $protocol;
    return $this;
  }

  public function getProtocol() {
    return $this->protocol;
  }

  public function setCredentialPHID($credential_phid) {
    $this->credentialPHID = $credential_phid;
    return $this;
  }

  public function getCredentialPHID() {
    return $this->credentialPHID;
  }

  public function setArgv(array $argv) {
    $this->argv = $argv;
    return $this;
  }

  public function getArgv() {
    return $this->argv;
  }

  public function setPassthru($passthru) {
    $this->passthru = $passthru;
    return $this;
  }

  public function getPassthru() {
    return $this->passthru;
  }

  public function newFuture() {
    $argv = $this->newCommandArgv();
    $env = $this->newCommandEnvironment();

    if ($this->getPassthru()) {
      $future = newv('PhutilExecPassthru', $argv);
    } else {
      $future = newv('ExecFuture', $argv);
    }

    $future->setEnv($env);

    return $future;
  }

  private function newCommandArgv() {
    $argv = $this->argv;
    $pattern = $argv[0];
    $argv = array_slice($argv, 1);

    list($pattern, $argv) = $this->newFormattedCommand($pattern, $argv);

    return array_merge(array($pattern), $argv);
  }

  private function newCommandEnvironment() {
    $env = $this->newCommonEnvironment() + $this->newCustomEnvironment();
    foreach ($env as $key => $value) {
      if ($value === null) {
        unset($env[$key]);
      }
    }
    return $env;
  }

  private function newCommonEnvironment() {
    $env = array();
      // NOTE: Force the language to "en_US.UTF-8", which overrides locale
      // settings. This makes stuff print in English instead of, e.g., French,
      // so we can parse the output of some commands, error messages, etc.
    $env['LANG'] = 'en_US.UTF-8';

      // Propagate PHABRICATOR_ENV explicitly. For discussion, see T4155.
    $env['PHABRICATOR_ENV'] = PhabricatorEnv::getSelectedEnvironmentName();

    if ($this->isAnySSHProtocol()) {
      $credential_phid = $this->getCredentialPHID();
      if ($credential_phid) {
        $env['PHABRICATOR_CREDENTIAL'] = $credential_phid;
      }
    }

    return $env;
  }

  protected function isSSHProtocol() {
    return ($this->getProtocol() == 'ssh');
  }

  protected function isSVNProtocol() {
    return ($this->getProtocol() == 'svn');
  }

  protected function isSVNSSHProtocol() {
    return ($this->getProtocol() == 'svn+ssh');
  }

  protected function isHTTPProtocol() {
    return ($this->getProtocol() == 'http');
  }

  protected function isHTTPSProtocol() {
    return ($this->getProtocol() == 'https');
  }

  protected function isAnyHTTPProtocol() {
    return ($this->isHTTPProtocol() || $this->isHTTPSProtocol());
  }

  protected function isAnySSHProtocol() {
    return ($this->isSSHProtocol() || $this->isSVNSSHProtocol());
  }

  protected function getSSHWrapper() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/bin/ssh-connect';
  }

}
