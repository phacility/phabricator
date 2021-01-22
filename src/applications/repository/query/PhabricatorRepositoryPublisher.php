<?php

final class PhabricatorRepositoryPublisher
  extends Phobject {

  private $repository;

  const HOLD_IMPORTING = 'auto/importing';
  const HOLD_PUBLISHING_DISABLED = 'auto/disabled';
  const HOLD_REF_NOT_BRANCH = 'not-branch';
  const HOLD_NOT_REACHABLE_FROM_PERMANENT_REF = 'auto/nobranch';
  const HOLD_UNTRACKED = 'auto/notrack';
  const HOLD_NOT_PERMANENT_REF = 'auto/noclose';

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    if (!$this->repository) {
      throw new PhutilInvalidStateException('setRepository');
    }
    return $this->repository;
  }

/* -(  Publishing  )--------------------------------------------------------- */

  public function shouldPublishRepository() {
    return !$this->getRepositoryHoldReasons();
  }

  public function shouldPublishRef(DiffusionRepositoryRef $ref) {
    return !$this->getRefHoldReasons($ref);
  }

  public function shouldPublishCommit(PhabricatorRepositoryCommit $commit) {
    return !$this->getCommitHoldReasons($commit);
  }

  public function isPermanentRef(DiffusionRepositoryRef $ref) {
    return !$this->getRefImpermanentReasons($ref);
  }

/* -(  Hold Reasons  )------------------------------------------------------- */

  public function getRepositoryHoldReasons() {
    $repository = $this->getRepository();

    $reasons = array();
    if ($repository->isImporting()) {
      $reasons[] = self::HOLD_IMPORTING;
    }

    if ($repository->isPublishingDisabled()) {
      $reasons[] = self::HOLD_PUBLISHING_DISABLED;
    }

    return $reasons;
  }

  public function getRefHoldReasons(DiffusionRepositoryRef $ref) {
    $repository = $this->getRepository();
    $reasons = $this->getRepositoryHoldReasons();

    foreach ($this->getRefImpermanentReasons($ref) as $reason) {
      $reasons[] = $reason;
    }

    return $reasons;
  }

  public function getCommitHoldReasons(PhabricatorRepositoryCommit $commit) {
    $repository = $this->getRepository();
    $reasons = $this->getRepositoryHoldReasons();

    if ($repository->isGit()) {
      if (!$commit->isPermanentCommit()) {
        $reasons[] = self::HOLD_NOT_REACHABLE_FROM_PERMANENT_REF;
      }
    }

    return $reasons;
  }

  public function getRefImpermanentReasons(DiffusionRepositoryRef $ref) {
    $repository = $this->getRepository();
    $reasons = array();

    if (!$ref->isBranch()) {
      $reasons[] = self::HOLD_REF_NOT_BRANCH;
    } else {
      $branch_name = $ref->getShortName();

      if (!$repository->shouldTrackBranch($branch_name)) {
        $reasons[] = self::HOLD_UNTRACKED;
      }

      if (!$repository->isBranchPermanentRef($branch_name)) {
        $reasons[] = self::HOLD_NOT_PERMANENT_REF;
      }
    }

    return $reasons;
  }

}
