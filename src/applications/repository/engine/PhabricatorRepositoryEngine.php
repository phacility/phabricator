<?php

/**
 * @task config     Configuring Repository Engines
 * @task internal   Internals
 */
abstract class PhabricatorRepositoryEngine extends Phobject {

  private $repository;
  private $verbose;

  /**
   * @task config
   */
  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }


  /**
   * @task config
   */
  protected function getRepository() {
    if ($this->repository === null) {
      throw new PhutilInvalidStateException('setRepository');
    }

    return $this->repository;
  }


  /**
   * @task config
   */
  public function setVerbose($verbose) {
    $this->verbose = $verbose;
    return $this;
  }


  /**
   * @task config
   */
  public function getVerbose() {
    return $this->verbose;
  }


  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  protected function newRepositoryLock(
    PhabricatorRepository $repository,
    $lock_key,
    $lock_device_only) {

    $lock_parts = array(
      'repositoryPHID' => $repository->getPHID(),
    );

    if ($lock_device_only) {
      $device = AlmanacKeys::getLiveDevice();
      if ($device) {
        $lock_parts['devicePHID'] = $device->getPHID();
      }
    }

    return PhabricatorGlobalLock::newLock($lock_key, $lock_parts);
  }

  /**
   * @task internal
   */
  protected function log($pattern /* ... */) {
    if ($this->getVerbose()) {
      $console = PhutilConsole::getConsole();
      $argv = func_get_args();
      array_unshift($argv, "%s\n");
      call_user_func_array(array($console, 'writeOut'), $argv);
    }
    return $this;
  }

}
