<?php

final class DiffusionSvnRequest extends DiffusionRequest {

  public function supportsBranches() {
    return false;
  }

  protected function isStableCommit($symbol) {
    return preg_match('/^[1-9]\d*\z/', $symbol);
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

}
