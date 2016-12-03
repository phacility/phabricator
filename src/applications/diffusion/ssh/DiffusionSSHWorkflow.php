<?php

abstract class DiffusionSSHWorkflow extends PhabricatorSSHWorkflow {

  private $args;
  private $repository;
  private $hasWriteAccess;
  private $proxyURI;
  private $baseRequestPath;

  public function getRepository() {
    if (!$this->repository) {
      throw new Exception(pht('Repository is not available yet!'));
    }
    return $this->repository;
  }

  private function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getArgs() {
    return $this->args;
  }

  public function getEnvironment() {
    $env = array(
      DiffusionCommitHookEngine::ENV_USER => $this->getUser()->getUsername(),
      DiffusionCommitHookEngine::ENV_REMOTE_PROTOCOL => 'ssh',
    );

    $remote_address = $this->getSSHRemoteAddress();
    if ($remote_address !== null) {
      $env[DiffusionCommitHookEngine::ENV_REMOTE_ADDRESS] = $remote_address;
    }

    return $env;
  }

  /**
   * Identify and load the affected repository.
   */
  abstract protected function identifyRepository();
  abstract protected function executeRepositoryOperations();
  abstract protected function raiseWrongVCSException(
    PhabricatorRepository $repository);

  protected function getBaseRequestPath() {
    return $this->baseRequestPath;
  }

  protected function writeError($message) {
    $this->getErrorChannel()->write($message);
    return $this;
  }

  protected function getCurrentDeviceName() {
    $device = AlmanacKeys::getLiveDevice();
    if ($device) {
      return $device->getName();
    }

    return php_uname('n');
  }

  protected function getTargetDeviceName() {
    // TODO: This should use the correct device identity.
    $uri = new PhutilURI($this->proxyURI);
    return $uri->getDomain();
  }

  protected function shouldProxy() {
    return (bool)$this->proxyURI;
  }

  protected function getProxyCommand() {
    $uri = new PhutilURI($this->proxyURI);

    $username = AlmanacKeys::getClusterSSHUser();
    if ($username === null) {
      throw new Exception(
        pht(
          'Unable to determine the username to connect with when trying '.
          'to proxy an SSH request within the Phabricator cluster.'));
    }

    $port = $uri->getPort();
    $host = $uri->getDomain();
    $key_path = AlmanacKeys::getKeyPath('device.key');
    if (!Filesystem::pathExists($key_path)) {
      throw new Exception(
        pht(
          'Unable to proxy this SSH request within the cluster: this device '.
          'is not registered and has a missing device key (expected to '.
          'find key at "%s").',
          $key_path));
    }

    $options = array();
    $options[] = '-o';
    $options[] = 'StrictHostKeyChecking=no';
    $options[] = '-o';
    $options[] = 'UserKnownHostsFile=/dev/null';

    // This is suppressing "added <address> to the list of known hosts"
    // messages, which are confusing and irrelevant when they arise from
    // proxied requests. It might also be suppressing lots of useful errors,
    // of course. Ideally, we would enforce host keys eventually.
    $options[] = '-o';
    $options[] = 'LogLevel=quiet';

    // NOTE: We prefix the command with "@username", which the far end of the
    // connection will parse in order to act as the specified user. This
    // behavior is only available to cluster requests signed by a trusted
    // device key.

    return csprintf(
      'ssh %Ls -l %s -i %s -p %s %s -- %s %Ls',
      $options,
      $username,
      $key_path,
      $port,
      $host,
      '@'.$this->getUser()->getUsername(),
      $this->getOriginalArguments());
  }

  final public function execute(PhutilArgumentParser $args) {
    $this->args = $args;

    $viewer = $this->getUser();
    $have_diffusion = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDiffusionApplication',
      $viewer);
    if (!$have_diffusion) {
      throw new Exception(
        pht(
          'You do not have permission to access the Diffusion application, '.
          'so you can not interact with repositories over SSH.'));
    }

    $repository = $this->identifyRepository();
    $this->setRepository($repository);

    $is_cluster_request = $this->getIsClusterRequest();
    $uri = $repository->getAlmanacServiceURI(
      $viewer,
      $is_cluster_request,
      array(
        'ssh',
      ));

    if ($uri) {
      $this->proxyURI = $uri;
    }

    try {
      return $this->executeRepositoryOperations();
    } catch (Exception $ex) {
      $this->writeError(get_class($ex).': '.$ex->getMessage());
      return 1;
    }
  }

  protected function loadRepositoryWithPath($path, $vcs) {
    $viewer = $this->getUser();

    $info = PhabricatorRepository::parseRepositoryServicePath($path, $vcs);
    if ($info === null) {
      throw new Exception(
        pht(
          'Unrecognized repository path "%s". Expected a path like "%s", '.
          '"%s", or "%s".',
          $path,
          '/diffusion/X/',
          '/diffusion/123/',
          '/source/thaumaturgy.git'));
    }

    $identifier = $info['identifier'];
    $base = $info['base'];

    $this->baseRequestPath = $base;

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withIdentifiers(array($identifier))
      ->needURIs(true)
      ->executeOne();
    if (!$repository) {
      throw new Exception(
        pht('No repository "%s" exists!', $identifier));
    }

    $protocol = PhabricatorRepositoryURI::BUILTIN_PROTOCOL_SSH;
    if (!$repository->canServeProtocol($protocol, false)) {
      throw new Exception(
        pht(
          'This repository ("%s") is not available over SSH.',
          $repository->getDisplayName()));
    }

    if ($repository->getVersionControlSystem() != $vcs) {
      $this->raiseWrongVCSException($repository);
    }

    return $repository;
  }

  protected function requireWriteAccess($protocol_command = null) {
    if ($this->hasWriteAccess === true) {
      return;
    }

    $repository = $this->getRepository();
    $viewer = $this->getUser();

    if ($viewer->isOmnipotent()) {
      throw new Exception(
        pht(
          'This request is authenticated as a cluster device, but is '.
          'performing a write. Writes must be performed with a real '.
          'user account.'));
    }

    $protocol = PhabricatorRepositoryURI::BUILTIN_PROTOCOL_SSH;
    if ($repository->canServeProtocol($protocol, true)) {
      $can_push = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $repository,
        DiffusionPushCapability::CAPABILITY);
      if (!$can_push) {
        throw new Exception(
          pht('You do not have permission to push to this repository.'));
      }
    } else {
      if ($protocol_command !== null) {
        throw new Exception(
          pht(
            'This repository is read-only over SSH (tried to execute '.
            'protocol command "%s").',
            $protocol_command));
      } else {
        throw new Exception(
          pht('This repository is read-only over SSH.'));
      }
    }

    $this->hasWriteAccess = true;
    return $this->hasWriteAccess;
  }

  protected function shouldSkipReadSynchronization() {
    $viewer = $this->getUser();

    // Currently, the only case where devices interact over SSH without
    // assuming user credentials is when synchronizing before a read. These
    // synchronizing reads do not themselves need to be synchronized.
    if ($viewer->isOmnipotent()) {
      return true;
    }

    return false;
  }

  protected function newPullEvent() {
    $viewer = $this->getViewer();
    $repository = $this->getRepository();
    $remote_address = $this->getSSHRemoteAddress();

    return id(new PhabricatorRepositoryPullEvent())
      ->setEpoch(PhabricatorTime::getNow())
      ->setRemoteAddress($remote_address)
      ->setRemoteProtocol('ssh')
      ->setPullerPHID($viewer->getPHID())
      ->setRepositoryPHID($repository->getPHID());
  }

}
