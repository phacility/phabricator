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

}
