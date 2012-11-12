<?php

/**
 * @group diffusion
 */
final class DiffusionSvnRequest extends DiffusionRequest {

  protected function getSupportsBranches() {
    return false;
  }

  protected function didInitialize() {
    if ($this->path === null) {
      $subpath = $this->repository->getDetail('svn-subpath');
      if ($subpath) {
        $this->path = $subpath;
      }
    }
  }

  protected function getArcanistBranch() {
    return 'svn';
  }

  public function getStableCommitName() {
    if ($this->commit) {
      return $this->commit;
    }

    if ($this->stableCommitName === null) {
      $commit = id(new PhabricatorRepositoryCommit())
        ->loadOneWhere(
          'repositoryID = %d ORDER BY epoch DESC LIMIT 1',
          $this->getRepository()->getID());
      if ($commit) {
        $this->stableCommitName = $commit->getCommitIdentifier();
      } else {
        // For new repositories, we may not have parsed any commits yet. Call
        // the stable commit "1" and avoid fataling.
        $this->stableCommitName = 1;
      }
    }

    return $this->stableCommitName;
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }

    return $this->getStableCommitName();
  }

}
