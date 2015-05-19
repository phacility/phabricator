<?php

final class DiffusionRenameHistoryQuery {

  private $oldCommit;
  private $wasCreated;
  private $request;
  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getWasCreated() {
    return $this->wasCreated;
  }

  public function setRequest(DiffusionRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function setOldCommit($old_commit) {
    $this->oldCommit = $old_commit;
    return $this;
  }

  public function getOldCommit() {
    return $this->oldCommit;
  }

  public function loadOldFilename() {
    $drequest = $this->request;
    $repository_id = $drequest->getRepository()->getID();
    $conn_r = id(new PhabricatorRepository())->establishConnection('r');

    $commit_id = $this->loadCommitId($this->oldCommit);
    $old_commit_sequence = $this->loadCommitSequence($commit_id);

    $path = '/'.$drequest->getPath();
    $commit_id = $this->loadCommitId($drequest->getCommit());

    do {
      $commit_sequence = $this->loadCommitSequence($commit_id);
      $change = queryfx_one(
        $conn_r,
        'SELECT pc.changeType, pc.targetCommitID, tp.path
         FROM %T p
         JOIN %T pc ON p.id = pc.pathID
         LEFT JOIN %T tp ON pc.targetPathID = tp.id
         WHERE p.pathHash = %s
         AND pc.repositoryID = %d
         AND pc.changeType IN (%d, %d)
         AND pc.commitSequence BETWEEN %d AND %d
         ORDER BY pc.commitSequence DESC
         LIMIT 1',
        PhabricatorRepository::TABLE_PATH,
        PhabricatorRepository::TABLE_PATHCHANGE,
        PhabricatorRepository::TABLE_PATH,
        md5($path),
        $repository_id,
        ArcanistDiffChangeType::TYPE_MOVE_HERE,
        ArcanistDiffChangeType::TYPE_ADD,
        $old_commit_sequence,
        $commit_sequence);
      if ($change) {
        if ($change['changeType'] == ArcanistDiffChangeType::TYPE_ADD) {
          $this->wasCreated = true;
          return $path;
        }
        $commit_id = $change['targetCommitID'];
        $path = $change['path'];
      }
    } while ($change && $path);

    return $path;
  }

  private function loadCommitId($commit_identifier) {
    $commit = id(new DiffusionCommitQuery())
      ->setViewer($this->viewer)
      ->withIdentifiers(array($commit_identifier))
      ->withRepository($this->request->getRepository())
      ->executeOne();
    return $commit->getID();
  }

  private function loadCommitSequence($commit_id) {
    $conn_r = id(new PhabricatorRepository())->establishConnection('r');
    $path_change = queryfx_one(
      $conn_r,
      'SELECT commitSequence
       FROM %T
       WHERE repositoryID = %d AND commitID = %d
       LIMIT 1',
      PhabricatorRepository::TABLE_PATHCHANGE,
      $this->request->getRepository()->getID(),
      $commit_id);
    return reset($path_change);
  }

}
