<?php

abstract class DiffusionSSHWorkflow extends PhabricatorSSHWorkflow {

  private $args;
  private $repository;
  private $hasWriteAccess;

  public function getRepository() {
    if (!$this->repository) {
      throw new Exception("Call loadRepository() before getRepository()!");
    }
    return $this->repository;
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

  abstract protected function executeRepositoryOperations();

  protected function writeError($message) {
    $this->getErrorChannel()->write($message);
    return $this;
  }

  final public function execute(PhutilArgumentParser $args) {
    $this->args = $args;

    try {
      return $this->executeRepositoryOperations();
    } catch (Exception $ex) {
      $this->writeError(get_class($ex).': '.$ex->getMessage());
      return 1;
    }
  }

  protected function loadRepository($path) {
    $viewer = $this->getUser();

    $regex = '@^/?diffusion/(?P<callsign>[A-Z]+)(?:/|\z)@';
    $matches = null;
    if (!preg_match($regex, $path, $matches)) {
      throw new Exception(
        pht(
          'Unrecognized repository path "%s". Expected a path like '.
          '"%s".',
          $path,
          "/diffusion/X/"));
    }

    $callsign = $matches[1];
    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withCallsigns(array($callsign))
      ->executeOne();

    if (!$repository) {
      throw new Exception(
        pht('No repository "%s" exists!', $callsign));
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
          pht('This repository is not available over SSH.'));
    }

    $this->repository = $repository;

    return $repository;
  }

  protected function requireWriteAccess($protocol_command = null) {
    if ($this->hasWriteAccess === true) {
      return;
    }

    $repository = $this->getRepository();
    $viewer = $this->getUser();

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
          DiffusionCapabilityPush::CAPABILITY);
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

}
