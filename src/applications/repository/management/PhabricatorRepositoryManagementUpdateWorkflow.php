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
          'Update __repository__, named by callsign. '.
          'This performs the __pull__, __discover__, __ref__ and __mirror__ '.
          'operations and is primarily an internal workflow.'))
      ->setArguments(
        array(
          array(
            'name'        => 'verbose',
            'help'        => 'Show additional debugging information.',
          ),
          array(
            'name'        => 'no-discovery',
            'help'        => 'Do not perform discovery.',
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

    $repos = $this->loadRepositories($args, 'repos');
    if (count($repos) !== 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one repository to update, by callsign.'));
    }

    $repository = head($repos);

    try {
      $lock_name = 'repository.update:'.$repository->getID();
      $lock = PhabricatorGlobalLock::newLock($lock_name);

      try {
        $lock->lock();
      } catch (PhutilLockException $ex) {
        throw new PhutilProxyException(
          pht(
            'Another process is currently holding the update lock for '.
            'repository "%s". Repositories may only be updated by one '.
            'process at a time. This can happen if you are running multiple '.
            'copies of the daemons. This can also happen if you manually '.
            'update a repository while the daemons are also updating it '.
            '(in this case, just try again in a few moments).',
            $repository->getMonogram()),
          $ex);
      }

      try {
        $no_discovery = $args->getArg('no-discovery');

        id(new PhabricatorRepositoryPullEngine())
          ->setRepository($repository)
          ->setVerbose($this->getVerbose())
          ->pullRepository();

        if ($no_discovery) {
          $lock->unlock();
          return;
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
      } catch (Exception $ex) {
        $lock->unlock();
        throw $ex;
      }
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

    $lock->unlock();

    $console->writeOut(
      pht(
        'Updated repository **%s**.',
        $repository->getMonogram())."\n");

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

    // Look for any commit which hasn't imported.
    $unparsed_commit = queryfx_one(
      $repository->establishConnection('r'),
      'SELECT * FROM %T WHERE repositoryID = %d AND (importStatus & %d) != %d
        LIMIT 1',
      id(new PhabricatorRepositoryCommit())->getTableName(),
      $repository->getID(),
      PhabricatorRepositoryCommit::IMPORTED_ALL,
      PhabricatorRepositoryCommit::IMPORTED_ALL);
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
