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

    $ssh_client = getenv('SSH_CLIENT');
    if ($ssh_client) {
      // This has the format "<ip> <remote-port> <local-port>". Grab the IP.
      $remote_address = head(explode(' ', $ssh_client));
      $env[DiffusionCommitHookEngine::ENV_REMOTE_ADDRESS] = $remote_address;
    }

    return $env;
  }

  /**
   * Identify and load the affected repository.
   */
  abstract protected function identifyRepository();
  abstract protected function executeRepositoryOperations();

  protected function getBaseRequestPath() {
    return $this->baseRequestPath;
  }

  protected function writeError($message) {
    $this->getErrorChannel()->write($message);
    return $this;
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

  protected function loadRepositoryWithPath($path) {
    $viewer = $this->getUser();

    $info = PhabricatorRepository::parseRepositoryServicePath($path);
    if ($info === null) {
      throw new Exception(
        pht(
          'Unrecognized repository path "%s". Expected a path like "%s" '.
          'or "%s".',
          $path,
          '/diffusion/X/',
          '/diffusion/123/'));
    }

    $identifier = $info['identifier'];
    $base = $info['base'];

    $this->baseRequestPath = $base;

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withIdentifiers(array($identifier))
      ->executeOne();
    if (!$repository) {
      throw new Exception(
        pht('No repository "%s" exists!', $identifier));
    }

    switch ($repository->getServeOverSSH()) {
      case PhabricatorRepository::SERVE_READONLY:
      case PhabricatorRepository::SERVE_READWRITE:
        // If we have read or read/write access, proceed for now. We will
        // check write access when the user actually issues a write command.
        break;
      case PhabricatorRepository::SERVE_OFF:
      default:
        throw new Exception(
          pht(
            'This repository ("%s") is not available over SSH.',
            $repository->getDisplayName()));
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

    switch ($repository->getServeOverSSH()) {
      case PhabricatorRepository::SERVE_READONLY:
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
        break;
      case PhabricatorRepository::SERVE_READWRITE:
        $can_push = PhabricatorPolicyFilter::hasCapability(
          $viewer,
          $repository,
          DiffusionPushCapability::CAPABILITY);
        if (!$can_push) {
          throw new Exception(
            pht('You do not have permission to push to this repository.'));
        }
        break;
      case PhabricatorRepository::SERVE_OFF:
      default:
        // This shouldn't be reachable because we don't get this far if the
        // repository isn't enabled, but kick them out anyway.
        throw new Exception(
          pht('This repository is not available over SSH.'));
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


}
