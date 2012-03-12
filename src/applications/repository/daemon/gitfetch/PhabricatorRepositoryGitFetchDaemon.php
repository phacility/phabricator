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

final class PhabricatorRepositoryGitFetchDaemon
  extends PhabricatorRepositoryPullLocalDaemon {

  protected function getSupportedRepositoryType() {
    return PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
  }

  protected function executeCreate(
    PhabricatorRepository $repository,
    $local_path) {

    $repository->execxRemoteCommand(
      'clone --origin origin %s %s',
      $repository->getRemoteURI(),
      rtrim($local_path, '/'));
  }

  protected function executeUpdate(
    PhabricatorRepository $repository,
    $local_path) {

    // Run a bunch of sanity checks to detect people checking out repositories
    // inside other repositories, making empty directories, pointing the local
    // path at some random file or path, etc.

    list($err, $stdout) = $repository->execLocalCommand(
      'rev-parse --show-toplevel');

    if ($err) {

      // Try to raise a more tailored error message in the more common case
      // of the user creating an empty directory. (We could try to remove it,
      // but might not be able to, and it's much simpler to raise a good
      // message than try to navigate those waters.)
      if (is_dir($local_path)) {
        $files = Filesystem::listDirectory($local_path, $include_hidden = true);
        if (!$files) {
          throw new Exception(
            "Expected to find a git repository at '{$local_path}', but there ".
            "is an empty directory there. Remove the directory: the daemon ".
            "will run 'git clone' for you.");
        }
      }

      throw new Exception(
        "Expected to find a git repository at '{$local_path}', but there is ".
        "a non-repository directory (with other stuff in it) there. Move or ".
        "remove this directory (or reconfigure the repository to use a ".
        "different directory), and then either clone a repository yourself ".
        "or let the daemon do it.");
    } else {
      $repo_path = rtrim($stdout, "\n");

      if (empty($repo_path)) {
        throw new Exception(
          "Expected to find a git repository at '{$local_path}', but ".
          "there was no result from `git rev-parse --show-toplevel`. ".
          "Something is misconfigured or broken. The git repository ".
          "may be inside a '.git/' directory.");
      }

      if (!Filesystem::pathsAreEquivalent($repo_path, $local_path)) {
        throw new Exception(
          "Expected to find repo at '{$local_path}', but the actual ".
          "git repository root for this directory is '{$repo_path}'. ".
          "Something is misconfigured. The repository's 'Local Path' should ".
          "be set to some place where the daemon can check out a working ".
          "copy, and should not be inside another git repository.");
      }
    }


    // This is a local command, but needs credentials.
    $future = $repository->getRemoteCommandFuture('fetch --all --prune');
    $future->setCWD($local_path);
    $future->resolvex();
  }

}
