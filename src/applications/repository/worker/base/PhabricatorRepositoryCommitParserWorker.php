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

abstract class PhabricatorRepositoryCommitParserWorker
  extends PhabricatorWorker {

  protected $commit;
  protected $repository;

  final public function doWork() {
    $commit_id = idx($this->getTaskData(), 'commitID');
    if (!$commit_id) {
      return;
    }

    $commit = id(new PhabricatorRepositoryCommit())->load($commit_id);

    if (!$commit) {
      // TODO: Communicate permanent failure?
      return;
    }

    $this->commit = $commit;

    $repository = id(new PhabricatorRepository())->load(
      $commit->getRepositoryID());

    if (!$repository) {
      return;
    }

    $this->repository = $repository;

    return $this->parseCommit($repository, $commit);
  }

  final protected function shouldQueueFollowupTasks() {
    return !idx($this->getTaskData(), 'only');
  }

  abstract protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  /**
   * This method is kind of awkward here but both the SVN message and
   * change parsers use it.
   */
  protected function getSVNLogXMLObject($uri, $revision, $verbose = false) {

    if ($verbose) {
      $verbose = '--verbose';
    }

    try {
      list($xml) = $this->repository->execxRemoteCommand(
        "log --xml {$verbose} --limit 1 %s@%d",
        $uri,
        $revision);
    } catch (CommandException $ex) {
      // HTTPS is generally faster and more reliable than svn+ssh, but some
      // commit messages with non-UTF8 text can't be retrieved over HTTPS, see
      // Facebook rE197184 for one example. Make an attempt to fall back to
      // svn+ssh if we've failed outright to retrieve the message.
      $fallback_uri = new PhutilURI($uri);
      if ($fallback_uri->getProtocol() != 'https') {
        throw $ex;
      }
      $fallback_uri->setProtocol('svn+ssh');
      list($xml) = execx(
        "svn log --xml {$verbose} --limit 1 --non-interactive %s@%d",
        $fallback_uri,
        $revision);
    }

    // Subversion may send us back commit messages which won't parse because
    // they have non UTF-8 garbage in them. Slam them into valid UTF-8.
    $xml = phutil_utf8ize($xml);

    return new SimpleXMLElement($xml);
  }

  protected function isBadCommit($full_commit_name) {
    $repository = new PhabricatorRepository();

    $bad_commit = queryfx_one(
      $repository->establishConnection('w'),
      'SELECT * FROM %T WHERE fullCommitName = %s',
      PhabricatorRepository::TABLE_BADCOMMIT,
      $full_commit_name);

    return (bool)$bad_commit;
  }

}
