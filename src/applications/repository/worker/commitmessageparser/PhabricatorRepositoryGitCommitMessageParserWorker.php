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

final class PhabricatorRepositoryGitCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    // NOTE: %B was introduced somewhat recently in git's history, so pull
    // commit message information with %s and %b instead.
    // Even though we pass --encoding here, git doesn't always succeed, so
    // we try a little harder, since git *does* tell us what the actual encoding
    // is correctly (unless it doesn't; encoding is sometimes empty).
    list($info) = $repository->execxLocalCommand(
      'log -n 1 --encoding=%s --format=%s %s --',
      'UTF-8',
      implode('%x00', array('%e', '%cn', '%ce', '%an', '%ae', '%s%n%n%b')),
      $commit->getCommitIdentifier());

    $parts = explode("\0", $info);
    $encoding = array_shift($parts);

    foreach ($parts as $key => $part) {
      if ($encoding) {
        $part = phutil_utf8_convert($part, 'UTF-8', $encoding);
      }
      $parts[$key] = phutil_utf8ize($part);
    }

    $committer_name   = $parts[0];
    $committer_email  = $parts[1];
    $author_name      = $parts[2];
    $author_email     = $parts[3];
    $message          = $parts[4];

    if (strlen($author_email)) {
      $author = "{$author_name} <{$author_email}>";
    } else {
      $author = "{$author_name}";
    }

    if (strlen($committer_email)) {
      $committer = "{$committer_name} <{$committer_email}>";
    } else {
      $committer = "{$committer_name}";
    }

    if ($committer == $author) {
      $committer = null;
    }

    $this->updateCommitData($author, $message, $committer);

    if ($this->shouldQueueFollowupTasks()) {
      PhabricatorWorker::scheduleTask(
        'PhabricatorRepositoryGitCommitChangeParserWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

  protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    list($stdout) = $repository->execxLocalCommand(
      'log -n 1 --format=%s %s --',
      '%T',
      $commit->getCommitIdentifier());

    $commit_hash = $commit->getCommitIdentifier();
    $tree_hash = trim($stdout);

    return array(
      array(ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT,
            $commit_hash),
      array(ArcanistDifferentialRevisionHash::HASH_GIT_TREE,
            $tree_hash),
    );
  }

}
