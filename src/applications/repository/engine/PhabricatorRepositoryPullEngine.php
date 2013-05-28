<?php

/**
 * Manages execution of `git pull` and `hg pull` commands for
 * @{class:PhabricatorRepository} objects. Used by
 * @{class:PhabricatorRepositoryPullLocalDaemon}.
 *
 * @task pull     Pulling Working Copies
 * @task git      Pulling Git Working Copies
 * @task hg       Pulling Mercurial Working Copies
 * @task internal Internals
 */
final class PhabricatorRepositoryPullEngine
  extends PhabricatorRepositoryEngine {


/* -(  Pulling Working Copies  )--------------------------------------------- */


  public function pullRepository() {
    $repository = $this->getRepository();

    $is_hg = false;
    $is_git = false;

    $vcs = $repository->getVersionControlSystem();
    $callsign = $repository->getCallsign();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // We never pull a local copy of Subversion repositories.
        $this->log(
          "Repository '%s' is a Subversion repository, which does not require ".
          "a local working copy to be pulled.",
          $callsign);
        return;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $is_hg = true;
        break;
      default:
        throw new Exception("Unsupported VCS '{$vcs}'!");
    }

    $callsign = $repository->getCallsign();
    $local_path = $repository->getLocalPath();
    if ($local_path === null) {
      throw new Exception(
        "No local path is configured for repository '{$callsign}'.");
    }

    $dirname = dirname($local_path);
    if (!Filesystem::pathExists($dirname)) {
      Filesystem::createDirectory($dirname, 0755, $recursive = true);
    }

    if (!Filesystem::pathExists($local_path)) {
      $this->log(
        "Creating a new working copy for repository '%s'.",
        $callsign);
      if ($is_git) {
        $this->executeGitCreate();
      } else {
        $this->executeMercurialCreate();
      }
    } else {
      $this->log(
        "Updating the working copy for repository '%s'.",
        $callsign);
      if ($is_git) {
        $this->executeGitUpdate();
      } else {
        $this->executeMercurialUpdate();
      }
    }

    return $this;
  }


/* -(  Pulling Git Working Copies  )----------------------------------------- */


  /**
   * @task git
   */
  private function executeGitCreate() {
    $repository = $this->getRepository();

    $repository->execxRemoteCommand(
      'clone --origin origin %s %s',
      $repository->getRemoteURI(),
      rtrim($repository->getLocalPath(), '/'));
  }


  /**
   * @task git
   */
  private function executeGitUpdate() {
    $repository = $this->getRepository();

    list($err, $stdout) = $repository->execLocalCommand(
      'rev-parse --show-toplevel');

    $message = null;
    $path = $repository->getLocalPath();
    if ($err) {
      // Try to raise a more tailored error message in the more common case
      // of the user creating an empty directory. (We could try to remove it,
      // but might not be able to, and it's much simpler to raise a good
      // message than try to navigate those waters.)
      if (is_dir($path)) {
        $files = Filesystem::listDirectory($path, $include_hidden = true);
        if (!$files) {
          $message =
            "Expected to find a git repository at '{$path}', but there ".
            "is an empty directory there. Remove the directory: the daemon ".
            "will run 'git clone' for you.";
        } else {
          $message =
            "Expected to find a git repository at '{$path}', but there is ".
            "a non-repository directory (with other stuff in it) there. Move ".
            "or remove this directory (or reconfigure the repository to use a ".
            "different directory), and then either clone a repository ".
            "yourself or let the daemon do it.";
        }
      } else if (is_file($path)) {
        $message =
          "Expected to find a git repository at '{$path}', but there is a ".
          "file there instead. Remove it and let the daemon clone a ".
          "repository for you.";
      } else {
        $message =
          "Expected to find a git repository at '{$path}', but did not.";
      }
    } else {
      $repo_path = rtrim($stdout, "\n");

      if (empty($repo_path)) {
        $err = true;
        $message =
          "Expected to find a git repository at '{$path}', but ".
          "there was no result from `git rev-parse --show-toplevel`. ".
          "Something is misconfigured or broken. The git repository ".
          "may be inside a '.git/' directory.";
      } else if (!Filesystem::pathsAreEquivalent($repo_path, $path)) {
        $err = true;
        $message =
          "Expected to find repo at '{$path}', but the actual ".
          "git repository root for this directory is '{$repo_path}'. ".
          "Something is misconfigured. The repository's 'Local Path' should ".
          "be set to some place where the daemon can check out a working ".
          "copy, and should not be inside another git repository.";
      }
    }

    if ($err && $this->canDestroyWorkingCopy($path)) {
      phlog("Repository working copy at '{$path}' failed sanity check; ".
            "destroying and re-cloning. {$message}");
      Filesystem::remove($path);
      $this->executeGitCreate();
    } else if ($err) {
      throw new Exception($message);
    }

    $retry = false;
    do {
      // This is a local command, but needs credentials.
      $future = $repository->getRemoteCommandFuture('fetch --all --prune');
      $future->setCWD($path);
      list($err, $stdout, $stderr) = $future->resolve();

      if ($err && !$retry && $this->canDestroyWorkingCopy($path)) {
        $retry = true;
        // Fix remote origin url if it doesn't match our configuration
        $origin_url = $repository->execLocalCommand(
          'config --get remote.origin.url');
        $remote_uri = $repository->getDetail('remote-uri');
        if ($origin_url != $remote_uri) {
          $repository->execLocalCommand(
            'remote set-url origin %s',
            $remote_uri);
        }
      } else if ($err) {
        throw new Exception(
          "git fetch failed with error #{$err}:\n".
          "stdout:{$stdout}\n\n".
          "stderr:{$stderr}\n");
      } else {
        $retry = false;
      }
    } while ($retry);
  }


/* -(  Pulling Mercurial Working Copies  )----------------------------------- */


  /**
   * @task hg
   */
  private function executeMercurialCreate() {
    $repository = $this->getRepository();

    $repository->execxRemoteCommand(
      'clone %s %s',
      $repository->getRemoteURI(),
      rtrim($repository->getLocalPath(), '/'));
  }


  /**
   * @task hg
   */
  private function executeMercurialUpdate() {
    $repository = $this->getRepository();
    $path = $repository->getLocalPath();

    // This is a local command, but needs credentials.
    $future = $repository->getRemoteCommandFuture('pull -u');
    $future->setCWD($path);

    try {
      $future->resolvex();
    } catch (CommandException $ex) {
      $err = $ex->getError();
      $stdout = $ex->getStdOut();

      // NOTE: Between versions 2.1 and 2.1.1, Mercurial changed the behavior
      // of "hg pull" to return 1 in case of a successful pull with no changes.
      // This behavior has been reverted, but users who updated between Feb 1,
      // 2012 and Mar 1, 2012 will have the erroring version. Do a dumb test
      // against stdout to check for this possibility.
      // See: https://github.com/facebook/phabricator/issues/101/

      // NOTE: Mercurial has translated versions, which translate this error
      // string. In a translated version, the string will be something else,
      // like "aucun changement trouve". There didn't seem to be an easy way
      // to handle this (there are hard ways but this is not a common problem
      // and only creates log spam, not application failures). Assume English.

      // TODO: Remove this once we're far enough in the future that deployment
      // of 2.1 is exceedingly rare?
      if ($err == 1 && preg_match('/no changes found/', $stdout)) {
        return;
      } else {
        throw $ex;
      }
    }
  }


/* -(  Internals  )---------------------------------------------------------- */


  private function canDestroyWorkingCopy($path) {
    $default_path = PhabricatorEnv::getEnvConfig(
      'repository.default-local-path');
    return Filesystem::isDescendant($path, $default_path);
  }

}
