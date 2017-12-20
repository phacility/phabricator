<?php

/**
 * Manages execution of `git pull` and `hg pull` commands for
 * @{class:PhabricatorRepository} objects. Used by
 * @{class:PhabricatorRepositoryPullLocalDaemon}.
 *
 * This class also covers initial working copy setup through `git clone`,
 * `git init`, `hg clone`, `hg init`, or `svnadmin create`.
 *
 * @task pull     Pulling Working Copies
 * @task git      Pulling Git Working Copies
 * @task hg       Pulling Mercurial Working Copies
 * @task svn      Pulling Subversion Working Copies
 * @task internal Internals
 */
final class PhabricatorRepositoryPullEngine
  extends PhabricatorRepositoryEngine {


/* -(  Pulling Working Copies  )--------------------------------------------- */


  public function pullRepository() {
    $repository = $this->getRepository();

    $lock = $this->newRepositoryLock($repository, 'repo.pull', true);

    try {
      $lock->lock();
    } catch (PhutilLockException $ex) {
      throw new DiffusionDaemonLockException(
        pht(
          'Another process is currently updating repository "%s", '.
          'skipping pull.',
          $repository->getDisplayName()));
    }

    try {
      $result = $this->pullRepositoryWithLock();
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();

    return $result;
  }

  private function pullRepositoryWithLock() {
    $repository = $this->getRepository();
    $viewer = PhabricatorUser::getOmnipotentUser();

    $is_hg = false;
    $is_git = false;
    $is_svn = false;

    $vcs = $repository->getVersionControlSystem();

    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // We never pull a local copy of non-hosted Subversion repositories.
        if (!$repository->isHosted()) {
          $this->skipPull(
            pht(
              'Repository "%s" is a non-hosted Subversion repository, which '.
              'does not require a local working copy to be pulled.',
              $repository->getDisplayName()));
          return;
        }
        $is_svn = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $is_hg = true;
        break;
      default:
        $this->abortPull(pht('Unknown VCS "%s"!', $vcs));
        break;
    }

    $local_path = $repository->getLocalPath();
    if ($local_path === null) {
      $this->abortPull(
        pht(
          'No local path is configured for repository "%s".',
          $repository->getDisplayName()));
    }

    try {
      $dirname = dirname($local_path);
      if (!Filesystem::pathExists($dirname)) {
        Filesystem::createDirectory($dirname, 0755, $recursive = true);
      }

      if (!Filesystem::pathExists($local_path)) {
        $this->logPull(
          pht(
            'Creating a new working copy for repository "%s".',
            $repository->getDisplayName()));
        if ($is_git) {
          $this->executeGitCreate();
        } else if ($is_hg) {
          $this->executeMercurialCreate();
        } else {
          $this->executeSubversionCreate();
        }
      }

      id(new DiffusionRepositoryClusterEngine())
        ->setViewer($viewer)
        ->setRepository($repository)
        ->synchronizeWorkingCopyBeforeRead();

      if (!$repository->isHosted()) {
        $this->logPull(
          pht(
            'Updating the working copy for repository "%s".',
            $repository->getDisplayName()));

        if ($is_git) {
          $this->verifyGitOrigin($repository);
          $this->executeGitUpdate();
        } else if ($is_hg) {
          $this->executeMercurialUpdate();
        }
      }

      if ($repository->isHosted()) {
        if ($is_git) {
          $this->installGitHook();
        } else if ($is_svn) {
          $this->installSubversionHook();
        } else if ($is_hg) {
          $this->installMercurialHook();
        }

        foreach ($repository->getHookDirectories() as $directory) {
          $this->installHookDirectory($directory);
        }
      }

    } catch (Exception $ex) {
      $this->abortPull(
        pht(
          "Pull of '%s' failed: %s",
          $repository->getDisplayName(),
          $ex->getMessage()),
        $ex);
    }

    $this->donePull();

    return $this;
  }

  private function skipPull($message) {
    $this->log($message);
    $this->donePull();
  }

  private function abortPull($message, Exception $ex = null) {
    $code_error = PhabricatorRepositoryStatusMessage::CODE_ERROR;
    $this->updateRepositoryInitStatus($code_error, $message);
    if ($ex) {
      throw $ex;
    } else {
      throw new Exception($message);
    }
  }

  private function logPull($message) {
    $this->log($message);
  }

  private function donePull() {
    $code_okay = PhabricatorRepositoryStatusMessage::CODE_OKAY;
    $this->updateRepositoryInitStatus($code_okay);
  }

  private function updateRepositoryInitStatus($code, $message = null) {
    $this->getRepository()->writeStatusMessage(
      PhabricatorRepositoryStatusMessage::TYPE_INIT,
      $code,
      array(
        'message' => $message,
      ));
  }

  private function installHook($path, array $hook_argv = array()) {
    $this->log(pht('Installing commit hook to "%s"...', $path));

    $repository = $this->getRepository();
    $identifier = $this->getHookContextIdentifier($repository);

    $root = dirname(phutil_get_library_root('phabricator'));
    $bin = $root.'/bin/commit-hook';

    $full_php_path = Filesystem::resolveBinary('php');
    $cmd = csprintf(
      'exec %s -f %s -- %s %Ls "$@"',
      $full_php_path,
      $bin,
      $identifier,
      $hook_argv);

    $hook = "#!/bin/sh\nexport TERM=dumb\n{$cmd}\n";

    Filesystem::writeFile($path, $hook);
    Filesystem::changePermissions($path, 0755);
  }

  private function installHookDirectory($path) {
    $readme = pht(
      "To add custom hook scripts to this repository, add them to this ".
      "directory.\n\nPhabricator will run any executables in this directory ".
      "after running its own checks, as though they were normal hook ".
      "scripts.");

    Filesystem::createDirectory($path, 0755);
    Filesystem::writeFile($path.'/README', $readme);
  }

  private function getHookContextIdentifier(PhabricatorRepository $repository) {
    $identifier = $repository->getPHID();

    $instance = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($instance)) {
      $identifier = "{$identifier}:{$instance}";
    }

    return $identifier;
  }


/* -(  Pulling Git Working Copies  )----------------------------------------- */


  /**
   * @task git
   */
  private function executeGitCreate() {
    $repository = $this->getRepository();

    $path = rtrim($repository->getLocalPath(), '/');

    if ($repository->isHosted()) {
      $repository->execxRemoteCommand(
        'init --bare -- %s',
        $path);
    } else {
      $repository->execxRemoteCommand(
        'clone --bare -- %P %s',
        $repository->getRemoteURIEnvelope(),
        $path);
    }
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
          $message = pht(
            "Expected to find a git repository at '%s', but there ".
            "is an empty directory there. Remove the directory: the daemon ".
            "will run '%s' for you.",
            $path,
            'git clone');
        } else {
          $message = pht(
            "Expected to find a git repository at '%s', but there is ".
            "a non-repository directory (with other stuff in it) there. Move ".
            "or remove this directory (or reconfigure the repository to use a ".
            "different directory), and then either clone a repository ".
            "yourself or let the daemon do it.",
            $path);
        }
      } else if (is_file($path)) {
        $message = pht(
          "Expected to find a git repository at '%s', but there is a ".
          "file there instead. Remove it and let the daemon clone a ".
          "repository for you.",
          $path);
      } else {
        $message = pht(
          "Expected to find a git repository at '%s', but did not.",
          $path);
      }
    } else {
      $repo_path = rtrim($stdout, "\n");

      if (empty($repo_path)) {
        // This can mean one of two things: we're in a bare repository, or
        // we're inside a git repository inside another git repository. Since
        // the first is dramatically more likely now that we perform bare
        // clones and I don't have a great way to test for the latter, assume
        // we're OK.
      } else if (!Filesystem::pathsAreEquivalent($repo_path, $path)) {
        $err = true;
        $message = pht(
          "Expected to find repo at '%s', but the actual git repository root ".
          "for this directory is '%s'. Something is misconfigured. ".
          "The repository's 'Local Path' should be set to some place where ".
          "the daemon can check out a working copy, ".
          "and should not be inside another git repository.",
          $path,
          $repo_path);
      }
    }

    if ($err && $repository->canDestroyWorkingCopy()) {
      phlog(
        pht(
          "Repository working copy at '%s' failed sanity check; ".
          "destroying and re-cloning. %s",
          $path,
          $message));
      Filesystem::remove($path);
      $this->executeGitCreate();
    } else if ($err) {
      throw new Exception($message);
    }

    $remote_refs = $this->loadGitRemoteRefs($repository);
    $local_refs = $this->loadGitLocalRefs($repository);
    if ($remote_refs === $local_refs) {
      $this->log(
        pht(
          'Skipping fetch because local and remote refs are already '.
          'identical.'));
      return false;
    }

    $this->logRefDifferences($remote_refs, $local_refs);

    // Force the "origin" URI to the configured value.
    $repository->execxLocalCommand(
      'remote set-url origin -- %P',
      $repository->getRemoteURIEnvelope());

    if ($repository->isWorkingCopyBare()) {
      // For bare working copies, we need this magic incantation.
      $future = $repository->getRemoteCommandFuture(
        'fetch origin %s --prune',
        '+refs/*:refs/*');
    } else {
      $future = $repository->getRemoteCommandFuture(
        'fetch --all --prune');
    }

    $future
      ->setCWD($path)
      ->resolvex();
  }


  /**
   * @task git
   */
  private function installGitHook() {
    $repository = $this->getRepository();
    $root = $repository->getLocalPath();

    if ($repository->isWorkingCopyBare()) {
      $path = '/hooks/pre-receive';
    } else {
      $path = '/.git/hooks/pre-receive';
    }

    $this->installHook($root.$path);
  }

  private function loadGitRemoteRefs(PhabricatorRepository $repository) {
    $remote_envelope = $repository->getRemoteURIEnvelope();

    // NOTE: "git ls-remote" does not support "--" until circa January 2016.
    // See T12416. None of the flags to "ls-remote" appear dangerous, and
    // other checks make it difficult to configure a suspicious remote URI.
    list($stdout) = $repository->execxRemoteCommand(
      'ls-remote %P',
      $remote_envelope);

    $map = array();
    $lines = phutil_split_lines($stdout, false);
    foreach ($lines as $line) {
      list($hash, $name) = preg_split('/\s+/', $line, 2);

      // If the remote has a HEAD, just ignore it.
      if ($name == 'HEAD') {
        continue;
      }

      // If the remote ref is itself a remote ref, ignore it.
      if (preg_match('(^refs/remotes/)', $name)) {
        continue;
      }

      $map[$name] = $hash;
    }

    ksort($map);

    return $map;
  }

  private function loadGitLocalRefs(PhabricatorRepository $repository) {
    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->execute();

    $map = array();
    foreach ($refs as $ref) {
      $fields = $ref->getRawFields();
      $map[idx($fields, 'refname')] = $ref->getCommitIdentifier();
    }

    ksort($map);

    return $map;
  }

  private function logRefDifferences(array $remote, array $local) {
    $all = $local + $remote;

    $differences = array();
    foreach ($all as $key => $ignored) {
      $remote_ref = idx($remote, $key, pht('<null>'));
      $local_ref = idx($local, $key, pht('<null>'));
      if ($remote_ref !== $local_ref) {
        $differences[] = pht(
          '%s (remote: "%s", local: "%s")',
          $key,
          $remote_ref,
          $local_ref);
      }
    }

    $this->log(
      pht(
        "Updating repository after detecting ref differences:\n%s",
        implode("\n", $differences)));
  }



/* -(  Pulling Mercurial Working Copies  )----------------------------------- */


  /**
   * @task hg
   */
  private function executeMercurialCreate() {
    $repository = $this->getRepository();

    $path = rtrim($repository->getLocalPath(), '/');

    if ($repository->isHosted()) {
      $repository->execxRemoteCommand(
        'init -- %s',
        $path);
    } else {
      $remote = $repository->getRemoteURIEnvelope();

      // NOTE: Mercurial prior to 3.2.4 has an severe command injection
      // vulnerability. See: <http://bit.ly/19B58E9>

      // On vulnerable versions of Mercurial, we refuse to clone remotes which
      // contain characters which may be interpreted by the shell.
      $hg_binary = PhutilBinaryAnalyzer::getForBinary('hg');
      $is_vulnerable = $hg_binary->isMercurialVulnerableToInjection();
      if ($is_vulnerable) {
        $cleartext = $remote->openEnvelope();
        // The use of "%R" here is an attempt to limit collateral damage
        // for normal URIs because it isn't clear how long this vulnerability
        // has been around for.

        $escaped = csprintf('%R', $cleartext);
        if ((string)$escaped !== (string)$cleartext) {
          throw new Exception(
            pht(
              'You have an old version of Mercurial (%s) which has a severe '.
              'command injection security vulnerability. The remote URI for '.
              'this repository (%s) is potentially unsafe. Upgrade Mercurial '.
              'to at least 3.2.4 to clone it.',
              $hg_binary->getBinaryVersion(),
              $repository->getMonogram()));
        }
      }

      try {
        $repository->execxRemoteCommand(
          'clone --noupdate -- %P %s',
          $remote,
          $path);
      } catch (Exception $ex) {
        $message = $ex->getMessage();
        $message = $this->censorMercurialErrorMessage($message);
        throw new Exception($message);
      }
    }
  }


  /**
   * @task hg
   */
  private function executeMercurialUpdate() {
    $repository = $this->getRepository();
    $path = $repository->getLocalPath();

    // This is a local command, but needs credentials.
    $remote = $repository->getRemoteURIEnvelope();
    $future = $repository->getRemoteCommandFuture('pull -- %P', $remote);
    $future->setCWD($path);

    try {
      $future->resolvex();
    } catch (CommandException $ex) {
      $err = $ex->getError();
      $stdout = $ex->getStdout();

      // NOTE: Between versions 2.1 and 2.1.1, Mercurial changed the behavior
      // of "hg pull" to return 1 in case of a successful pull with no changes.
      // This behavior has been reverted, but users who updated between Feb 1,
      // 2012 and Mar 1, 2012 will have the erroring version. Do a dumb test
      // against stdout to check for this possibility.
      // See: https://github.com/phacility/phabricator/issues/101/

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
        $message = $ex->getMessage();
        $message = $this->censorMercurialErrorMessage($message);
        throw new Exception($message);
      }
    }
  }


  /**
   * Censor response bodies from Mercurial error messages.
   *
   * When Mercurial attempts to clone an HTTP repository but does not
   * receive a response it expects, it emits the response body in the
   * command output.
   *
   * This represents a potential SSRF issue, because an attacker with
   * permission to create repositories can create one which points at the
   * remote URI for some local service, then read the response from the
   * error message. To prevent this, censor response bodies out of error
   * messages.
   *
   * @param string Uncensored Mercurial command output.
   * @return string Censored Mercurial command output.
   */
  private function censorMercurialErrorMessage($message) {
    return preg_replace(
      '/^---%<---.*/sm',
      pht('<Response body omitted from Mercurial error message.>')."\n",
      $message);
  }


  /**
   * @task hg
   */
  private function installMercurialHook() {
    $repository = $this->getRepository();
    $path = $repository->getLocalPath().'/.hg/hgrc';

    $identifier = $this->getHookContextIdentifier($repository);

    $root = dirname(phutil_get_library_root('phabricator'));
    $bin = $root.'/bin/commit-hook';

    $data = array();
    $data[] = '[hooks]';

    // This hook handles normal pushes.
    $data[] = csprintf(
      'pretxnchangegroup.phabricator = TERM=dumb %s %s %s',
      $bin,
      $identifier,
      'pretxnchangegroup');

    // This one handles creating bookmarks.
    $data[] = csprintf(
      'prepushkey.phabricator = TERM=dumb %s %s %s',
      $bin,
      $identifier,
      'prepushkey');

    $data[] = null;

    $data = implode("\n", $data);

    $this->log('%s', pht('Installing commit hook config to "%s"...', $path));

    Filesystem::writeFile($path, $data);
  }


/* -(  Pulling Subversion Working Copies  )---------------------------------- */


  /**
   * @task svn
   */
  private function executeSubversionCreate() {
    $repository = $this->getRepository();

    $path = rtrim($repository->getLocalPath(), '/');
    execx('svnadmin create -- %s', $path);
  }


  /**
   * @task svn
   */
  private function installSubversionHook() {
    $repository = $this->getRepository();
    $root = $repository->getLocalPath();

    $path = '/hooks/pre-commit';
    $this->installHook($root.$path);

    $revprop_path = '/hooks/pre-revprop-change';

    $revprop_argv = array(
      '--hook-mode',
      'svn-revprop',
    );

    $this->installHook($root.$revprop_path, $revprop_argv);
  }


}
