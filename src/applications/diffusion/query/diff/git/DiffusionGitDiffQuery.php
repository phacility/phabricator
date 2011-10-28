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

final class DiffusionGitDiffQuery extends DiffusionDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    if (!$drequest->getRawCommit()) {
      $effective_commit = $this->getEffectiveCommit();
      if (!$effective_commit) {
        return null;
      }
      // TODO: This side effect is kind of skethcy.
      $drequest->setCommit($effective_commit);
    } else {
      $effective_commit = $drequest->getCommit();
    }

    $options = array(
      '-M',
      '-C',
      '--no-ext-diff',
      '--no-color',
      '--src-prefix=a/',
      '--dst-prefix=b/',
      '-U65535',
    );
    $options = implode(' ', $options);

    try {
      list($raw_diff) = execx(
        "(cd %s && git diff %C %s^ %s -- %s)",
        $repository->getDetail('local-path'),
        $options,
        $effective_commit,
        $effective_commit,
        $drequest->getPath());
    } catch (CommandException $ex) {
      // Check if this is the root commit by seeing if it has parents.
      list($parents) = execx(
        '(cd %s && git log --format=%s %s --)',
        $repository->getDetail('local-path'),
        '%P', // "parents"
        $effective_commit);
      if (!strlen(trim($parents))) {
        // No parents means we're looking at the root revision. Diff against
        // the empty tree hash instead, since there is no parent so "^" does
        // not work. See ArcanistGitAPI for more discussion.
        list($raw_diff) = execx(
          '(cd %s && git diff %C %s %s -- %s)',
          $repository->getDetail('local-path'),
          $options,
          ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT,
          $effective_commit,
          $drequest->getPath());
      } else {
        throw $ex;
      }
    }

    $parser = new ArcanistDiffParser();

    $try_encoding = $repository->getDetail('encoding');
    if ($try_encoding) {
      $parser->setTryEncoding($try_encoding);
    }

    $parser->setDetectBinaryFiles(true);
    $changes = $parser->parseDiff($raw_diff);

    $diff = DifferentialDiff::newFromRawChanges($changes);
    $changesets = $diff->getChangesets();
    $changeset = reset($changesets);

    $this->renderingReference =
      $drequest->getBranchURIComponent($drequest->getBranch()).
      $drequest->getPath().';'.
      $drequest->getCommit();

    return $changeset;
  }

}
