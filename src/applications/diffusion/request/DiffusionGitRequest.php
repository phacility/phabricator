<?php

final class DiffusionGitRequest extends DiffusionRequest {

  public function supportsBranches() {
    return true;
  }

  protected function isStableCommit($symbol) {
    return preg_match('/^[a-f0-9]{40}\z/', $symbol);
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }
    if ($this->repository) {
      return $this->repository->getDefaultBranch();
    }
    throw new Exception('Unable to determine branch!');
  }

  protected function getResolvableBranchName($branch) {
    if ($this->repository->isWorkingCopyBare()) {
      return $branch;
    } else {
      $remote = DiffusionGitBranch::DEFAULT_GIT_REMOTE;
      return $remote.'/'.$branch;
    }
  }

}
