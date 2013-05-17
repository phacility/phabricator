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

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }

    return $this->getStableCommitName();
  }

}
