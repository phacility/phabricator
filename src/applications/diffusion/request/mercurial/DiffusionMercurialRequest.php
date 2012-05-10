<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group diffusion
 */
final class DiffusionMercurialRequest extends DiffusionRequest {

  protected function getSupportsBranches() {
    return true;
  }

  protected function didInitialize() {
    $repository = $this->getRepository();

    if (!Filesystem::pathExists($repository->getLocalPath())) {
      $this->raiseCloneException();
    }

    return;
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }

    if ($this->repository) {
      return $this->repository->getDetail('default-branch', 'default');
    }

    throw new Exception("Unable to determine branch!");
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    return $this->getBranch();
  }

  public function getStableCommitName() {
    if (!$this->stableCommitName) {
      if ($this->commit) {
        $this->stableCommitName = $this->commit;
      } else {
        list($this->stableCommitName) = $this->repository->execxLocalCommand(
          'log --template=%s --rev %s',
          '{node}',
          $this->getBranch());
      }
    }
    return $this->stableCommitName;
  }

}
