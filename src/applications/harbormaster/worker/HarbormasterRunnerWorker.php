<?php

final class HarbormasterRunnerWorker extends PhabricatorWorker {

  public function getRequiredLeaseTime() {
    return 60 * 60 * 24;
  }

  protected function doWork() {
    $data = $this->getTaskData();
    $id = idx($data, 'commitID');

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'id = %d',
      $id);

    if (!$commit) {
      throw new PhabricatorWorkerPermanentFailureException(
        "Commit '{$id}' does not exist!");
    }

    $repository = id(new PhabricatorRepository())->loadOneWhere(
      'id = %d',
      $commit->getRepositoryID());

    if (!$repository) {
      throw new PhabricatorWorkerPermanentFailureException(
        "Unable to load repository for commit '{$id}'!");
    }

    $lease = id(new DrydockLease())
      ->setResourceType('working-copy')
      ->setAttributes(
        array(
          'repositoryID' => $repository->getID(),
          'commit' => $commit->getCommitIdentifier(),
        ))
      ->releaseOnDestruction()
      ->waitUntilActive();

    $cmd = $lease->getInterface('command');
    list($json) = $cmd
      ->setWorkingDirectory($lease->getResource()->getAttribute('path'))
      ->execx('arc unit --everything --json');
    $lease->release();

    // TODO: Do something actually useful with this. Requires Harbormaster
    // buildout.
    echo $json;
  }

}
