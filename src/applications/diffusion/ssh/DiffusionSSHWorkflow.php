<?php

abstract class DiffusionSSHWorkflow extends PhabricatorSSHWorkflow {

  private $args;

  public function getArgs() {
    return $this->args;
  }

  abstract protected function isReadOnly();
  abstract protected function getRequestPath();
  abstract protected function executeRepositoryOperations(
    PhabricatorRepository $repository);

  protected function writeError($message) {
    $this->getErrorChannel()->write($message);
    return $this;
  }

  final public function execute(PhutilArgumentParser $args) {
    $this->args = $args;

    try {
      $repository = $this->loadRepository();
      return $this->executeRepositoryOperations($repository);
    } catch (Exception $ex) {
      $this->writeError(get_class($ex).': '.$ex->getMessage());
      return 1;
    }
  }

  private function loadRepository() {
    $viewer = $this->getUser();
    $path = $this->getRequestPath();

    $regex = '@^/?diffusion/(?P<callsign>[A-Z]+)(?:/|$)@';
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

    $is_push = !$this->isReadOnly();

    switch ($repository->getServeOverSSH()) {
      case PhabricatorRepository::SERVE_READONLY:
        if ($is_push) {
          throw new Exception(
            pht('This repository is read-only over SSH.'));
        }
        break;
      case PhabricatorRepository::SERVE_READWRITE:
        if ($is_push) {
          $can_push = PhabricatorPolicyFilter::hasCapability(
            $viewer,
            $repository,
            DiffusionCapabilityPush::CAPABILITY);
          if (!$can_push) {
            throw new Exception(
              pht('You do not have permission to push to this repository.'));
          }
        }
        break;
      case PhabricatorRepository::SERVE_OFF:
      default:
        throw new Exception(
          pht('This repository is not available over SSH.'));
    }

    return $repository;
  }

}
