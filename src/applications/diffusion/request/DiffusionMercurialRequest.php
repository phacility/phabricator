<?php

/**
 * @group diffusion
 */
final class DiffusionMercurialRequest extends DiffusionRequest {

  protected function getSupportsBranches() {
    return true;
  }

  protected function didInitialize() {
    // Expand abbreviated hashes to full hashes so "/rXnnnn" (i.e., fewer than
    // 40 characters) works correctly.
    if (!$this->commit) {
      return;
    }

    if (strlen($this->commit) == 40) {
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
