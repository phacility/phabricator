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

final class DiffusionGitRawDiffQuery extends DiffusionRawDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commit = $drequest->getCommit();

    $options = array(
      '-M',
      '-C',
      '--no-ext-diff',
      '--no-color',
      '--src-prefix=a/',
      '--dst-prefix=b/',
      '-U'.(int)$this->getLinesOfContext(),
    );
    $options = implode(' ', $options);

    $against = $this->getAgainstCommit();
    if ($against === null) {
      $against = $commit.'^';
    }

    // If there's no path, get the entire raw diff.
    $path = nonempty($drequest->getPath(), '.');

    $future = $repository->getLocalCommandFuture(
      "diff %C %s %s -- %s",
      $options,
      $against,
      $commit,
      $path);

    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }

    try {
      list($raw_diff) = $future->resolvex();
    } catch (CommandException $ex) {
      // Check if this is the root commit by seeing if it has parents.
      list($parents) = $repository->execxLocalCommand(
        'log --format=%s %s --',
        '%P', // "parents"
        $commit);

      if (strlen(trim($parents))) {
        throw $ex;
      }

      // No parents means we're looking at the root revision. Diff against
      // the empty tree hash instead, since there is no parent so "^" does
      // not work. See ArcanistGitAPI for more discussion.
      $future = $repository->getLocalCommandFuture(
        'diff %C %s %s -- %s',
        $options,
        ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT,
        $commit,
        $drequest->getPath());

      if ($this->getTimeout()) {
        $future->setTimeout($this->getTimeout());
      }

      list($raw_diff) = $future->resolvex();
    }

    return $raw_diff;
  }

}
