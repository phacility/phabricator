<?php

final class DiffusionMercurialRequest extends DiffusionRequest {

  protected function isStableCommit($symbol) {
    return $symbol !== null && preg_match('/^[a-f0-9]{40}\z/', $symbol);
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }

    if ($this->repository) {
      return $this->repository->getDefaultBranch();
    }

    throw new Exception(pht('Unable to determine branch!'));
  }

}
