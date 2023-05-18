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

    if ($repository->isReadOnly()) {
      $this->skipPull(
        pht(
          "Skipping pull on read-only repository.\n\n%s",
          $repository->getReadOnlyMessageForDisplay()));
    }

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

      if ($is_git) {
        $this->updateGitWorkingCopyConfiguration();
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
    if ($instance !== null && strlen($instance)) {
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

    // See T13448. In all cases, we create repositories by using "git init"
    // to build a bare, empty working copy. If we try to use "git clone"
    // instead, we'll pull in too many refs if "Fetch Refs" is also
    // configured. There's no apparent way to make "git clone" behave narrowly
    // and no apparent reason to bother.

    $repository->execxRemoteCommand(
      'init --bare -- %s',
      $path);
  }


  /**
   * @task git
   */
  private function executeGitUpdate() {
    $repository = $this->getRepository();

    // See T13479. We previously used "--show-toplevel", but this stopped
    // working in Git 2.25.0 when run in a bare repository.

    // NOTE: As of Git 2.21.1, "git rev-parse" can not parse "--" in its
    // argument list, so we can not specify arguments unambiguously. Any
    // version of Git which does not recognize the "--git-dir" flag will
    // treat this as a request to parse the literal refname "--git-dir".

    list($err, $stdout) = $repository->execLocalCommand(
      'rev-parse --git-dir');

    $repository_root = null;
    $path = $repository->getLocalPath();

    if (!$err) {
      $repository_root = Filesystem::resolvePath(
        rtrim($stdout, "\n"),
        $path);

      // If we're in a bare Git repository, the "--git-dir" will be the
      // root directory. If we're in a working copy, the "--git-dir" will
      // be the ".git/" directory.

      // Test if the result is the root directory. If it is, we're in good
      // shape and appear to be inside a bare repository. If not, take the
      // parent directory to get out of the ".git/" folder.

      if (!Filesystem::pathsAreEquivalent($repository_root, $path)) {
        $repository_root = dirname($repository_root);
      }
    }

    $message = null;
    if ($err) {
      // Try to raise a more tailored error message in the more common case
      // of the user creating an empty directory. (We could try to remove it,
      // but might not be able to, and it's much simpler to raise a good
      // message than try to navigate those waters.)
      if (is_dir($path)) {
        $files = Filesystem::listDirectory($path, $include_hidden = true);
        if (!$files) {
          $message = pht(
            'Expected to find a Git repository at "%s", but there is an '.
            'empty directory there. Remove the directory. A daemon will '.
            'construct the working copy for you.',
            $path);
        } else {
          $message = pht(
            'Expected to find a Git repository at "%s", but there is '.
            'a non-repository directory (with other stuff in it) there. '.
            'Move or remove this directory. A daemon will construct '.
            'the working copy for you.',
            $path);
        }
      } else if (is_file($path)) {
        $message = pht(
          'Expected to find a Git repository at "%s", but there is a '.
          'file there instead. Move or remove this file. A daemon will '.
          'construct the working copy for you.',
          $path);
      } else {
        $message = pht(
          'Expected to find a git repository at "%s", but did not.',
          $path);
      }
    } else {

      // Prior to Git 2.25.0, we used "--show-toplevel", which had a weird
      // case here when the working copy was inside another working copy.
      // The switch to "--git-dir" seems to have resolved this; we now seem
      // to find the nearest git directory and thus the correct repository
      // root.

      if (!Filesystem::pathsAreEquivalent($repository_root, $path)) {
        $err = true;
        $message = pht(
          'Expected to find a Git repository at "%s", but the actual Git '.
          'repository root for this directory is "%s". Something is '.
          'misconfigured. This directory should be writable by the daemons '.
          'and not inside another Git repository.',
          $path,
          $repository_root);
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

    // Load the refs we're planning to fetch from the remote repository.
    $remote_refs = $this->loadGitRemoteRefs(
      $repository,
      $repository->getRemoteURIEnvelope(),
      $is_local = false);

    // Load the refs we're planning to fetch from the local repository, by
    // using the local working copy path as the "remote" repository URI.
    $local_refs = $this->loadGitRemoteRefs(
      $repository,
      new PhutilOpaqueEnvelope($path),
      $is_local = true);

    // See T13448. The "git fetch --prune ..." flag only prunes local refs
    // matching the refspecs we pass it. If "Fetch Refs" is configured, we'll
    // pass it a very narrow list of refspecs, and it won't prune older refs
    // that aren't currently subject to fetching.

    // Since we want to prune everything that isn't (a) on the fetch list and
    // (b) in the remote, handle pruning of any surplus leftover refs ourselves
    // before we fetch anything.

    // (We don't have to do this if "Fetch Refs" isn't set up, since "--prune"
    // will work in that case, but it's a little simpler to always go down the
    // same code path.)

    $surplus_refs = array();
    foreach ($local_refs as $local_ref => $local_hash) {
      $remote_hash = idx($remote_refs, $local_ref);
      if ($remote_hash === null) {
        $surplus_refs[] = $local_ref;
      }
    }

    if ($surplus_refs) {
      $this->log(
        pht(
          'Found %s surplus local ref(s) to delete.',
          phutil_count($surplus_refs)));
      foreach ($surplus_refs as $surplus_ref) {
        $this->log(
          pht(
            'Deleting surplus local ref "%s" ("%s").',
            $surplus_ref,
            $local_refs[$surplus_ref]));

        $repository->execLocalCommand(
          'update-ref -d %R --',
          $surplus_ref);

        unset($local_refs[$surplus_ref]);
      }
    }

    if ($remote_refs === $local_refs) {
      $this->log(
        pht(
          'Skipping fetch because local and remote refs are already '.
          'identical.'));
      return false;
    }

    $this->logRefDifferences($remote_refs, $local_refs);

    $fetch_rules = $this->getGitFetchRules($repository);

    // For very old non-bare working copies, we need to use "--update-head-ok"
    // to tell Git that it is allowed to overwrite whatever is currently
    // checked out. See T13280.

    $future = $repository->getRemoteCommandFuture(
      'fetch --no-tags --update-head-ok -- %P %Ls',
      $repository->getRemoteURIEnvelope(),
      $fetch_rules);

    $future
      ->setCWD($path)
      ->resolvex();
  }

  private function getGitRefRules(PhabricatorRepository $repository) {
    $ref_rules = $repository->getFetchRules($repository);

    if (!$ref_rules) {
      $ref_rules = array(
        'refs/*',
      );
    }

    return $ref_rules;
  }

  private function getGitFetchRules(PhabricatorRepository $repository) {
    $ref_rules = $this->getGitRefRules($repository);

    // Rewrite each ref rule "X" into "+X:X".

    // The "X" means "fetch ref X".
    // The "...:X" means "...and copy it into local ref X".
    // The "+..." means "...and overwrite the local ref if it already exists".

    $fetch_rules = array();
    foreach ($ref_rules as $key => $ref_rule) {
      $fetch_rules[] = sprintf(
        '+%s:%s',
        $ref_rule,
        $ref_rule);
    }

    return $fetch_rules;
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

  private function updateGitWorkingCopyConfiguration() {
    $repository = $this->getRepository();

    // See T5963. When you "git clone" from a remote with no "master", the
    // client warns you that it isn't sure what it should check out as an
    // initial state:

    //   warning: remote HEAD refers to nonexistent ref, unable to checkout

    // We can tell the client what it should check out by making "HEAD"
    // point somewhere. However:
    //
    // (1) If we don't set "receive.denyDeleteCurrent" to "ignore" and a user
    // tries to delete the default branch, Git raises an error and refuses.
    // We want to allow this; we already have sufficient protections around
    // dangerous changes and do not need to special case the default branch.
    //
    // (2) A repository may have a nonexistent default branch configured.
    // For now, we just respect configuration. This will raise a warning when
    // users clone the repository.
    //
    // In any case, these changes are both advisory, so ignore any errors we
    // may encounter.

    // We do this for both hosted and observed repositories. Although it is
    // not terribly common to clone from Phabricator's copy of an observed
    // repository, it works fine and makes sense occasionally.

    if ($repository->isWorkingCopyBare()) {
      $repository->execLocalCommand(
        'config -- receive.denyDeleteCurrent ignore');
      $repository->execLocalCommand(
        'symbolic-ref HEAD %s',
        'refs/heads/'.$repository->getDefaultBranch());
    }
  }

  private function loadGitRemoteRefs(
    PhabricatorRepository $repository,
    PhutilOpaqueEnvelope $remote_envelope,
    $is_local) {

    // See T13448. When listing local remotes, we want to list everything,
    // not just refs we expect to fetch. This allows us to detect that we have
    // undesirable refs (which have been deleted in the remote, but are still
    // present locally) so we can update our state to reflect the correct
    // remote state.

    if ($is_local) {
      $ref_rules = array();
    } else {
      $ref_rules = $this->getGitRefRules($repository);

      // NOTE: "git ls-remote" does not support "--" until circa January 2016.
      // See T12416. None of the flags to "ls-remote" appear dangerous, but
      // refuse to list any refs beginning with "-" just in case.

      foreach ($ref_rules as $ref_rule) {
        if (preg_match('/^-/', $ref_rule)) {
          throw new Exception(
            pht(
              'Refusing to list potentially dangerous ref ("%s") beginning '.
              'with "-".',
              $ref_rule));
        }
      }
    }

    list($stdout) = $repository->execxRemoteCommand(
      'ls-remote %P %Ls',
      $remote_envelope,
      $ref_rules);

    // Empty repositories don't have any refs.
    if ($stdout === null || !strlen(rtrim($stdout))) {
      return array();
    }

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
