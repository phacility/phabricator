<?php

final class PhabricatorRepositoryManagementUpdateWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  private $verbose;

  public function setVerbose($verbose) {
    $this->verbose = $verbose;
    return $this;
  }

  public function getVerbose() {
    return $this->verbose;
  }

  protected function didConstruct() {
    $this
      ->setName('update')
      ->setExamples('**update** [options] __repository__')
      ->setSynopsis(
        pht(
          'Update __repository__. This performs the __pull__, __discover__, '.
          '__refs__ and __mirror__ operations and is primarily an internal '.
          'workflow.'))
      ->setArguments(
        array(
          array(
            'name'        => 'verbose',
            'help'        => pht('Show additional debugging information.'),
          ),
          array(
            'name'        => 'no-discovery',
            'help'        => pht('Do not perform discovery.'),
          ),
          array(
            'name'        => 'repos',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $this->setVerbose($args->getArg('verbose'));
    $console = PhutilConsole::getConsole();

    $repos = $this->loadLocalRepositories($args, 'repos');
    if (count($repos) !== 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one repository to update.'));
    }

    $repository = head($repos);

    try {
      id(new PhabricatorRepositoryPullEngine())
        ->setRepository($repository)
        ->setVerbose($this->getVerbose())
        ->pullRepository();

      $no_discovery = $args->getArg('no-discovery');
      if ($no_discovery) {
        return 0;
      }

      // TODO: It would be nice to discover only if we pulled something, but
      // this isn't totally trivial. It's slightly more complicated with
      // hosted repositories, too.

      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        null);

      $this->discoverRepository($repository);

      $this->checkIfRepositoryIsFullyImported($repository);

      $this->updateRepositoryRefs($repository);

      $this->mirrorRepository($repository);

      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_FETCH,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);
    } catch (DiffusionDaemonLockException $ex) {
      // If we miss a pull or discover because some other process is already
      // doing the work, just bail out.
      echo tsprintf(
        "%s\n",
        $ex->getMessage());
      return 0;
    } catch (Exception $ex) {
      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_FETCH,
        PhabricatorRepositoryStatusMessage::CODE_ERROR,
        array(
          'message' => pht(
            'Error updating working copy: %s', $ex->getMessage()),
        ));
      throw $ex;
    }

    echo tsprintf(
      "%s\n",
      pht(
        'Updated repository "%s".',
        $repository->getDisplayName()));

    return 0;
  }

  private function discoverRepository(PhabricatorRepository $repository) {
    $refs = id(new PhabricatorRepositoryDiscoveryEngine())
      ->setRepository($repository)
      ->setVerbose($this->getVerbose())
      ->discoverCommits();

    return (bool)count($refs);
  }

  private function mirrorRepository(PhabricatorRepository $repository) {
    try {
      id(new PhabricatorRepositoryMirrorEngine())
        ->setRepository($repository)
        ->pushToMirrors();
    } catch (Exception $ex) {
      // TODO: We should report these into the UI properly, but for now just
      // complain. These errors are much less severe than pull errors.
      $proxy = new PhutilProxyException(
        pht(
          'Error while pushing "%s" repository to mirrors.',
          $repository->getMonogram()),
        $ex);
      phlog($proxy);
    }
  }

  private function updateRepositoryRefs(PhabricatorRepository $repository) {
    id(new PhabricatorRepositoryRefEngine())
      ->setRepository($repository)
      ->updateRefs();
  }

  private function checkIfRepositoryIsFullyImported(
    PhabricatorRepository $repository) {

    // Check if the repository has the "Importing" flag set. We want to clear
    // the flag if we can.
    $importing = $repository->getDetail('importing');
    if (!$importing) {
      // This repository isn't marked as "Importing", so we're done.
      return;
    }

    // Look for any commit which is reachable and hasn't imported.
    $unparsed_commit = queryfx_one(
      $repository->establishConnection('r'),
      'SELECT * FROM %T WHERE repositoryID = %d
        AND (importStatus & %d) != %d
        AND (importStatus & %d) != %d
        LIMIT 1',
      id(new PhabricatorRepositoryCommit())->getTableName(),
      $repository->getID(),
      PhabricatorRepositoryCommit::IMPORTED_ALL,
      PhabricatorRepositoryCommit::IMPORTED_ALL,
      PhabricatorRepositoryCommit::IMPORTED_UNREACHABLE,
      PhabricatorRepositoryCommit::IMPORTED_UNREACHABLE);
    if ($unparsed_commit) {
      // We found a commit which still needs to import, so we can't clear the
      // flag.
      return;
    }

    // Clear the "importing" flag.
    $repository->openTransaction();
      $repository->beginReadLocking();
        $repository = $repository->reload();
        $repository->setDetail('importing', false);
        $repository->save();
      $repository->endReadLocking();
    $repository->saveTransaction();
  }


}
