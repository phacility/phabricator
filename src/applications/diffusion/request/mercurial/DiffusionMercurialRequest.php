<?php

/*
 * Copyright 2011 Facebook, Inc.
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

// TODO: This has some minor code duplication vs the Git request that could be
// shared.
class DiffusionMercurialRequest extends DiffusionRequest {

  protected function initializeFromAphrontRequestDictionary(array $data) {
    parent::initializeFromAphrontRequestDictionary($data);

    $path = $this->path;
    $parts = explode('/', $path);

    $branch = array_shift($parts);
    if ($branch != ':') {
      $this->branch = $this->decodeBranchName($branch);
    }

    foreach ($parts as $key => $part) {
      if ($part == '..') {
        unset($parts[$key]);
      }
    }

    $this->path = implode('/', $parts);
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

  public function getUriPath() {
    return '/diffusion/'.$this->getCallsign().'/browse/'.
      $this->getBranchURIComponent($this->branch).$this->path;
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    return $this->getBranch();
  }

  public function getStableCommitName() {
    return substr($this->stableCommitName, 0, 16);
  }

  public function getBranchURIComponent($branch) {
    return $this->encodeBranchName($branch).'/';
  }

  private function decodeBranchName($branch) {
    return str_replace(':', '/', $branch);
  }

  private function encodeBranchName($branch) {
    return str_replace('/', ':', $branch);
  }

}
