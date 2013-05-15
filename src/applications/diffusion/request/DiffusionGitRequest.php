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
    $remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE;
    return $remote.'/'.$this->getBranch();
  }

  public function getStableCommitName() {
    if (!$this->stableCommitName) {
      if ($this->commit) {
        $this->stableCommitName = $this->commit;
      } else {
        $branch = $this->getBranch();
        list($stdout) = $this->getRepository()->execxLocalCommand(
          'rev-parse --verify %s/%s',
          DiffusionBranchInformation::DEFAULT_GIT_REMOTE,
          $branch);
        $this->stableCommitName = trim($stdout);
      }
    }
    return substr($this->stableCommitName, 0, 16);
  }

}
