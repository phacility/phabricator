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

class DiffusionGitRequest extends DiffusionRequest {

  protected function initializeFromAphrontRequestDictionary() {
    parent::initializeFromAphrontRequestDictionary();

    $path = $this->path;
    $parts = explode('/', $path);

    $branch = array_shift($parts);
    $this->branch = $this->decodeBranchName($branch);

    $this->path = implode('/', $parts);

    if ($this->repository) {
      $local_path = $this->repository->getDetail('local-path');
      $git = $this->getPathToGitBinary();

      // TODO: This is not terribly efficient and does not produce terribly
      // good error messages, but it seems better to put error handling code
      // here than to try to do it in every query.

      $branch = $this->getBranch();

      execx(
        '(cd %s && %s rev-parse --verify %s)',
        $local_path,
        $git,
        $branch);

      if ($this->commit) {
        execx(
          '(cd %s && %s rev-parse --verify %s)',
          $local_path,
          $git,
          $this->commit);
        list($contains) = execx(
          '(cd %s && %s branch --contains %s)',
          $local_path,
          $git,
          $this->commit);
        $contains = array_filter(explode("\n", $contains));
        $found = false;
        foreach ($contains as $containing_branch) {
          $containing_branch = trim($containing_branch, "* \n");
          if ($containing_branch == $branch) {
            $found = true;
            break;
          }
        }
        if (!$found) {
          throw new Exception(
            "Commit does not exist on this branch!");
        }
      }
    }


  }

  public function getPathToGitBinary() {
    return PhabricatorEnv::getEnvConfig('git.path');
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }
    if ($this->repository) {
      return $this->repository->getDetail('default-branch', 'master');
    }
    throw new Exception("Unable to determine branch!");
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    return $this->getBranch();
  }

  private function decodeBranchName($branch) {
    return str_replace(':', '/', $branch);
  }

  private function encodeBranchName($branch) {
    return str_replace('/', ':', $branch);
  }

}
