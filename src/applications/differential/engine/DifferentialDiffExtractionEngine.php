<?php

final class DifferentialDiffExtractionEngine extends Phobject {

  private $viewer;
  private $authorPHID;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setAuthorPHID($author_phid) {
    $this->authorPHID = $author_phid;
    return $this;
  }

  public function getAuthorPHID() {
    return $this->authorPHID;
  }

  public function newDiffFromCommit(PhabricatorRepositoryCommit $commit) {
    $viewer = $this->getViewer();

    $repository = $commit->getRepository();
    $identifier = $commit->getCommitIdentifier();
    $monogram = $commit->getMonogram();

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $viewer,
        'repository' => $repository,
      ));

    $raw_diff = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      'diffusion.rawdiffquery',
      array(
        'commit' => $identifier,
      ));

    // TODO: Support adds, deletes and moves under SVN.
    if (strlen($raw_diff)) {
      $changes = id(new ArcanistDiffParser())->parseDiff($raw_diff);
    } else {
      // This is an empty diff, maybe made with `git commit --allow-empty`.
      // NOTE: These diffs have the same tree hash as their ancestors, so
      // they may attach to revisions in an unexpected way. Just let this
      // happen for now, although it might make sense to special case it
      // eventually.
      $changes = array();
    }

    $diff = DifferentialDiff::newFromRawChanges($viewer, $changes)
      ->setRepositoryPHID($repository->getPHID())
      ->setCreationMethod('commit')
      ->setSourceControlSystem($repository->getVersionControlSystem())
      ->setLintStatus(DifferentialLintStatus::LINT_AUTO_SKIP)
      ->setUnitStatus(DifferentialUnitStatus::UNIT_AUTO_SKIP)
      ->setDateCreated($commit->getEpoch())
      ->setDescription($monogram);

    $author_phid = $this->getAuthorPHID();
    if ($author_phid !== null) {
      $diff->setAuthorPHID($author_phid);
    }

    $parents = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      'diffusion.commitparentsquery',
      array(
        'commit' => $identifier,
      ));

    if ($parents) {
      $diff->setSourceControlBaseRevision(head($parents));
    }

    // TODO: Attach binary files.

    return $diff->save();
  }

}
