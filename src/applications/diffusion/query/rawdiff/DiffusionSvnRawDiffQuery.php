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

final class DiffusionSvnRawDiffQuery extends DiffusionRawDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commit = $drequest->getCommit();
    $arc_root = phutil_get_library_root('arcanist');

    $against = $this->getAgainstCommit();
    if ($against === null) {
      $against = $commit - 1;
    }

    $future = $repository->getRemoteCommandFuture(
      'diff --diff-cmd %s -x -U%d -r %d:%d %s%s@',
      $arc_root.'/../scripts/repository/binary_safe_diff.sh',
      $this->getLinesOfContext(),
      $against,
      $commit,
      $repository->getRemoteURI(),
      $drequest->getPath());

    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }

    list($raw_diff) = $future->resolvex();
    return $raw_diff;
  }

}
