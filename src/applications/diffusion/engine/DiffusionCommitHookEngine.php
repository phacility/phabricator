<?php

/**
 * @task config   Configuring the Hook Engine
 * @task hook     Hook Execution
 * @task git      Git Hooks
 * @task hg       Mercurial Hooks
 * @task svn      Subversion Hooks
 * @task internal Internals
 */
final class DiffusionCommitHookEngine extends Phobject {

  const ENV_USER = 'PHABRICATOR_USER';
  const ENV_REMOTE_ADDRESS = 'PHABRICATOR_REMOTE_ADDRESS';
  const ENV_REMOTE_PROTOCOL = 'PHABRICATOR_REMOTE_PROTOCOL';

  const EMPTY_HASH = '0000000000000000000000000000000000000000';

  private $viewer;
  private $repository;
  private $stdin;
  private $subversionTransaction;
  private $subversionRepository;
  private $remoteAddress;
  private $remoteProtocol;
  private $transactionKey;


/* -(  Config  )------------------------------------------------------------- */


  public function setRemoteProtocol($remote_protocol) {
    $this->remoteProtocol = $remote_protocol;
    return $this;
  }

  public function getRemoteProtocol() {
    return $this->remoteProtocol;
  }

  public function setRemoteAddress($remote_address) {
    $this->remoteAddress = $remote_address;
    return $this;
  }

  public function getRemoteAddress() {
    return $this->remoteAddress;
  }

  private function getRemoteAddressForLog() {
    // If whatever we have here isn't a valid IPv4 address, just store `null`.
    // Older versions of PHP return `-1` on failure instead of `false`.
    $remote_address = $this->getRemoteAddress();
    $remote_address = max(0, ip2long($remote_address));
    $remote_address = nonempty($remote_address, null);
    return $remote_address;
  }

  private function getTransactionKey() {
    if (!$this->transactionKey) {
      $entropy = Filesystem::readRandomBytes(64);
      $this->transactionKey = PhabricatorHash::digestForIndex($entropy);
    }
    return $this->transactionKey;
  }

  public function setSubversionTransactionInfo($transaction, $repository) {
    $this->subversionTransaction = $transaction;
    $this->subversionRepository = $repository;
    return $this;
  }

  public function setStdin($stdin) {
    $this->stdin = $stdin;
    return $this;
  }

  public function getStdin() {
    return $this->stdin;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }


/* -(  Hook Execution  )----------------------------------------------------- */


  public function execute() {
    $ref_updates = $this->findRefUpdates();
    $all_updates = $ref_updates;

    $caught = null;
    try {

      try {
        $this->rejectDangerousChanges($ref_updates);
      } catch (DiffusionCommitHookRejectException $ex) {
        // If we're rejecting dangerous changes, flag everything that we've
        // seen as rejected so it's clear that none of it was accepted.
        foreach ($all_updates as $update) {
          $update->setRejectCode(
            PhabricatorRepositoryPushLog::REJECT_DANGEROUS);
        }
        throw $ex;
      }

      // TODO: Fire ref herald rules.

      $content_updates = $this->findContentUpdates($ref_updates);
      $all_updates = array_merge($all_updates, $content_updates);

      // TODO: Fire content Herald rules.
      // TODO: Fire external hooks.

      // If we make it this far, we're accepting these changes. Mark all the
      // logs as accepted.
      foreach ($all_updates as $update) {
        $update->setRejectCode(PhabricatorRepositoryPushLog::REJECT_ACCEPT);
      }
    } catch (Exception $ex) {
      // We'll throw this again in a minute, but we want to save all the logs
      // first.
      $caught = $ex;
    }

    // Save all the logs no matter what the outcome was.
    foreach ($all_updates as $update) {
      $update->save();
    }

    if ($caught) {
      throw $caught;
    }

    return 0;
  }

  private function findRefUpdates() {
    $type = $this->getRepository()->getVersionControlSystem();
    switch ($type) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        return $this->findGitRefUpdates();
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return $this->findMercurialRefUpdates();
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return $this->findSubversionRefUpdates();
      default:
        throw new Exception(pht('Unsupported repository type "%s"!', $type));
    }
  }

  private function rejectDangerousChanges(array $ref_updates) {
    assert_instances_of($ref_updates, 'PhabricatorRepositoryPushLog');

    $repository = $this->getRepository();
    if ($repository->shouldAllowDangerousChanges()) {
      return;
    }

    $flag_dangerous = PhabricatorRepositoryPushLog::CHANGEFLAG_DANGEROUS;

    foreach ($ref_updates as $ref_update) {
      if (!$ref_update->hasChangeFlags($flag_dangerous)) {
        // This is not a dangerous change.
        continue;
      }

      // We either have a branch deletion or a non fast-forward branch update.
      // Format a message and reject the push.

      $message = pht(
        "DANGEROUS CHANGE: %s\n".
        "Dangerous change protection is enabled for this repository.\n".
        "Edit the repository configuration before making dangerous changes.",
        $ref_update->getDangerousChangeDescription());

      throw new DiffusionCommitHookRejectException($message);
    }
  }

  private function findContentUpdates(array $ref_updates) {
    assert_instances_of($ref_updates, 'PhabricatorRepositoryPushLog');

    $type = $this->getRepository()->getVersionControlSystem();
    switch ($type) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        return $this->findGitContentUpdates($ref_updates);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return $this->findMercurialContentUpdates($ref_updates);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return $this->findSubversionContentUpdates($ref_updates);
      default:
        throw new Exception(pht('Unsupported repository type "%s"!', $type));
    }
  }


/* -(  Git  )---------------------------------------------------------------- */


  private function findGitRefUpdates() {
    $ref_updates = array();

    // First, parse stdin, which lists all the ref changes. The input looks
    // like this:
    //
    //   <old hash> <new hash> <ref>

    $stdin = $this->getStdin();
    $lines = phutil_split_lines($stdin, $retain_endings = false);
    foreach ($lines as $line) {
      $parts = explode(' ', $line, 3);
      if (count($parts) != 3) {
        throw new Exception(pht('Expected "old new ref", got "%s".', $line));
      }

      $ref_old = $parts[0];
      $ref_new = $parts[1];
      $ref_raw = $parts[2];

      if (preg_match('(^refs/heads/)', $ref_raw)) {
        $ref_type = PhabricatorRepositoryPushLog::REFTYPE_BRANCH;
      } else if (preg_match('(^refs/tags/)', $ref_raw)) {
        $ref_type = PhabricatorRepositoryPushLog::REFTYPE_TAG;
      } else {
        $ref_type = PhabricatorRepositoryPushLog::REFTYPE_UNKNOWN;
      }

      $ref_update = $this->newPushLog()
        ->setRefType($ref_type)
        ->setRefName($ref_raw)
        ->setRefOld($ref_old)
        ->setRefNew($ref_new);

      $ref_updates[] = $ref_update;
    }

    $this->findGitMergeBases($ref_updates);
    $this->findGitChangeFlags($ref_updates);

    return $ref_updates;
  }


  private function findGitMergeBases(array $ref_updates) {
    assert_instances_of($ref_updates, 'PhabricatorRepositoryPushLog');

    $futures = array();
    foreach ($ref_updates as $key => $ref_update) {
      // If the old hash is "00000...", the ref is being created (either a new
      // branch, or a new tag). If the new hash is "00000...", the ref is being
      // deleted. If both are nonempty, the ref is being updated. For updates,
      // we'll figure out the `merge-base` of the old and new objects here. This
      // lets us reject non-FF changes cheaply; later, we'll figure out exactly
      // which commits are new.
      $ref_old = $ref_update->getRefOld();
      $ref_new = $ref_update->getRefNew();

      if (($ref_old === self::EMPTY_HASH) ||
          ($ref_new === self::EMPTY_HASH)) {
        continue;
      }

      $futures[$key] = $this->getRepository()->getLocalCommandFuture(
        'merge-base %s %s',
        $ref_old,
        $ref_new);
    }

    foreach (Futures($futures)->limit(8) as $key => $future) {

      // If 'old' and 'new' have no common ancestors (for example, a force push
      // which completely rewrites a ref), `git merge-base` will exit with
      // an error and no output. It would be nice to find a positive test
      // for this instead, but I couldn't immediately come up with one. See
      // T4224. Assume this means there are no ancestors.

      list($err, $stdout) = $future->resolve();

      if ($err) {
        $merge_base = null;
      } else {
        $merge_base = rtrim($stdout, "\n");
      }

      $ref_update->setMergeBase($merge_base);
    }

    return $ref_updates;
  }


  private function findGitChangeFlags(array $ref_updates) {
    assert_instances_of($ref_updates, 'PhabricatorRepositoryPushLog');

    foreach ($ref_updates as $key => $ref_update) {
      $ref_old = $ref_update->getRefOld();
      $ref_new = $ref_update->getRefNew();
      $ref_type = $ref_update->getRefType();

      $ref_flags = 0;
      $dangerous = null;

      if ($ref_old === self::EMPTY_HASH) {
        $ref_flags |= PhabricatorRepositoryPushLog::CHANGEFLAG_ADD;
      } else if ($ref_new === self::EMPTY_HASH) {
        $ref_flags |= PhabricatorRepositoryPushLog::CHANGEFLAG_DELETE;
        if ($ref_type == PhabricatorRepositoryPushLog::REFTYPE_BRANCH) {
          $ref_flags |= PhabricatorRepositoryPushLog::CHANGEFLAG_DANGEROUS;
          $dangerous = pht(
            "The change you're attempting to push deletes the branch '%s'.",
            $ref_update->getRefName());
        }
      } else {
        $merge_base = $ref_update->getMergeBase();
        if ($merge_base == $ref_old) {
          // This is a fast-forward update to an existing branch.
          // These are safe.
          $ref_flags |= PhabricatorRepositoryPushLog::CHANGEFLAG_APPEND;
        } else {
          $ref_flags |= PhabricatorRepositoryPushLog::CHANGEFLAG_REWRITE;

          // For now, we don't consider deleting or moving tags to be a
          // "dangerous" update. It's way harder to get wrong and should be easy
          // to recover from once we have better logging. Only add the dangerous
          // flag if this ref is a branch.

          if ($ref_type == PhabricatorRepositoryPushLog::REFTYPE_BRANCH) {
            $ref_flags |= PhabricatorRepositoryPushLog::CHANGEFLAG_DANGEROUS;

            $dangerous = pht(
              "DANGEROUS CHANGE: The change you're attempting to push updates ".
              "the branch '%s' from '%s' to '%s', but this is not a ".
              "fast-forward. Pushes which rewrite published branch history ".
              "are dangerous.",
              $ref_update->getRefName(),
              $ref_update->getRefOldShort(),
              $ref_update->getRefNewShort());
          }
        }
      }

      $ref_update->setChangeFlags($ref_flags);
      if ($dangerous !== null) {
        $ref_update->attachDangerousChangeDescription($dangerous);
      }
    }

    return $ref_updates;
  }


  private function findGitContentUpdates(array $ref_updates) {
    $flag_delete = PhabricatorRepositoryPushLog::CHANGEFLAG_DELETE;

    $futures = array();
    foreach ($ref_updates as $key => $ref_update) {
      if ($ref_update->hasChangeFlags($flag_delete)) {
        // Deleting a branch or tag can never create any new commits.
        continue;
      }

      // NOTE: This piece of magic finds all new commits, by walking backward
      // from the new value to the value of *any* existing ref in the
      // repository. Particularly, this will cover the cases of a new branch, a
      // completely moved tag, etc.
      $futures[$key] = $this->getRepository()->getLocalCommandFuture(
        'log --format=%s %s --not --all',
        '%H',
        $ref_update->getRefNew());
    }

    $content_updates = array();
    foreach (Futures($futures)->limit(8) as $key => $future) {
      list($stdout) = $future->resolvex();

      if (!strlen(trim($stdout))) {
        // This change doesn't have any new commits. One common case of this
        // is creating a new tag which points at an existing commit.
        continue;
      }

      $commits = phutil_split_lines($stdout, $retain_newlines = false);

      foreach ($commits as $commit) {
        $content_updates[$commit] = $this->newPushLog()
          ->setRefType(PhabricatorRepositoryPushLog::REFTYPE_COMMIT)
          ->setRefNew($commit)
          ->setChangeFlags(PhabricatorRepositoryPushLog::CHANGEFLAG_ADD);
      }
    }

    return $content_updates;
  }


/* -(  Mercurial  )---------------------------------------------------------- */


  private function findMercurialRefUpdates() {
    // TODO: Implement.
    return array();
  }

  private function findMercurialContentUpdates(array $ref_updates) {
    // TODO: Implement.
    return array();
  }


/* -(  Subversion  )--------------------------------------------------------- */


  private function findSubversionRefUpdates() {
    // TODO: Implement.
    return array();
  }

  private function findSubversionContentUpdates(array $ref_updates) {
    // TODO: Implement.
    return array();
  }


/* -(  Internals  )---------------------------------------------------------- */


  private function newPushLog() {
    // NOTE: By default, we create these with REJECT_BROKEN as the reject
    // code. This indicates a broken hook, and covers the case where we
    // encounter some unexpected exception and consequently reject the changes.

    return PhabricatorRepositoryPushLog::initializeNewLog($this->getViewer())
      ->attachRepository($this->getRepository())
      ->setRepositoryPHID($this->getRepository()->getPHID())
      ->setEpoch(time())
      ->setRemoteAddress($this->getRemoteAddressForLog())
      ->setRemoteProtocol($this->getRemoteProtocol())
      ->setTransactionKey($this->getTransactionKey())
      ->setRejectCode(PhabricatorRepositoryPushLog::REJECT_BROKEN)
      ->setRejectDetails(null);
  }

}
