<?php

/**
 * @group diffusion
 */
final class DiffusionGitRequest extends DiffusionRequest {

  protected function getSupportsBranches() {
    return true;
  }

  protected function didInitialize() {
    if (!$this->commit) {
      return;
    }

    $this->expandCommitName();
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }
    if ($this->repository) {
      return $this->repository->getDefaultBranch();
    }
    throw new Exception("Unable to determine branch!");
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    return $this->getBranch();
  }

}
