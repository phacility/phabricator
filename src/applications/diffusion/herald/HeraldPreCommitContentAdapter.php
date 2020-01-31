<?php

final class HeraldPreCommitContentAdapter extends HeraldPreCommitAdapter {

  private $changesets;
  private $commitRef;
  private $fields;
  private $revision = false;

  private $affectedPackages;
  private $identityCache = array();

  public function getAdapterContentName() {
    return pht('Commit Hook: Commit Content');
  }

  public function getAdapterSortOrder() {
    return 2500;
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to commits being pushed to hosted repositories.\n".
      "Hook rules can block changes and send push summary mail.");
  }

  public function isPreCommitRefAdapter() {
    return false;
  }

  public function getHeraldName() {
    return pht('Push Log (Content)');
  }

  public function isDiffEnormous() {
    $this->getDiffContent('*');
    return ($this->changesets instanceof Exception);
  }

  public function getDiffContent($type) {
    if ($this->changesets === null) {
      try {
        $this->changesets = $this->getHookEngine()->getChangesetsForCommit(
          $this->getObject()->getRefNew());
      } catch (Exception $ex) {
        $this->changesets = $ex;
      }
    }

    if ($this->changesets instanceof Exception) {
      $ex_class = get_class($this->changesets);
      $ex_message = $this->changesets->getMessage();
      if ($type === 'name') {
        return array("<{$ex_class}: {$ex_message}>");
      } else {
        return array("<{$ex_class}>" => $ex_message);
      }
    }

    $result = array();
    if ($type === 'name') {
      foreach ($this->changesets as $change) {
        $result[] = $change->getFilename();
      }
    } else {
      foreach ($this->changesets as $change) {
        $lines = array();
        foreach ($change->getHunks() as $hunk) {
          switch ($type) {
            case '-':
              $lines[] = $hunk->makeOldFile();
              break;
            case '+':
              $lines[] = $hunk->makeNewFile();
              break;
            case '*':
            default:
              $lines[] = $hunk->makeChanges();
              break;
          }
        }
        $result[$change->getFilename()] = implode('', $lines);
      }
    }

    return $result;
  }

  public function getCommitRef() {
    if ($this->commitRef === null) {
      $this->commitRef = $this->getHookEngine()->loadCommitRefForCommit(
        $this->getObject()->getRefNew());
    }
    return $this->commitRef;
  }

  public function getAuthorPHID() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $ref = $this->getCommitRef();
        $author = $ref->getAuthor();
        if (!strlen($author)) {
          return null;
        }
        return $this->lookupUser($author);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the author.
        return $this->getHookEngine()->getViewer()->getPHID();
    }
  }

  public function getCommitterPHID() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        // If there's no committer information, we're going to return the
        // author instead. However, if there's committer information and we
        // can't resolve it, return `null`.
        $ref = $this->getCommitRef();
        $committer = $ref->getCommitter();
        if (!strlen($committer)) {
          return $this->getAuthorPHID();
        }
        return $this->lookupUser($committer);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the committer.
        return $this->getHookEngine()->getViewer()->getPHID();
    }
  }

  public function getAuthorRaw() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $ref = $this->getCommitRef();
        return $ref->getAuthor();
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the author.
        return $this->getHookEngine()->getViewer()->getUsername();
    }
  }

  public function getCommitterRaw() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        // Here, if there's no committer, we're going to return the author
        // instead.
        $ref = $this->getCommitRef();
        $committer = $ref->getCommitter();
        if (strlen($committer)) {
          return $committer;
        }
        return $ref->getAuthor();
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the committer.
        return $this->getHookEngine()->getViewer()->getUsername();
    }
  }

  private function lookupUser($raw_identity) {
    // See T13480. After the move to repository identities, we want to look
    // users up in the identity table. If you push a commit which is authored
    // by "A Duck <duck@example.org>" and that identity is bound to user
    // "@mallard" in the identity table, Herald should see the author of the
    // commit as "@mallard" when evaluating pre-commit content rules.

    if (!array_key_exists($raw_identity, $this->identityCache)) {
      $repository = $this->getHookEngine()->getRepository();
      $viewer = $this->getHookEngine()->getViewer();

      $identity_engine = id(new DiffusionRepositoryIdentityEngine())
        ->setViewer($viewer);

      // We must provide a "sourcePHID" when resolving identities, but don't
      // have a legitimate one yet. Just use the repository PHID as a
      // reasonable value. This won't actually be written to storage.
      $source_phid = $repository->getPHID();
      $identity_engine->setSourcePHID($source_phid);

      // If the identity doesn't exist yet, we don't want to create it if
      // we haven't seen it before. It will be created later when we actually
      // import the commit.
      $identity_engine->setDryRun(true);

      $author_identity = $identity_engine->newResolvedIdentity($raw_identity);

      $effective_phid = $author_identity->getCurrentEffectiveUserPHID();

      $this->identityCache[$raw_identity] = $effective_phid;
    }

    return $this->identityCache[$raw_identity];
  }

  private function getCommitFields() {
    if ($this->fields === null) {
      $this->fields = id(new DiffusionLowLevelCommitFieldsQuery())
        ->setRepository($this->getHookEngine()->getRepository())
        ->withCommitRef($this->getCommitRef())
        ->execute();
    }
    return $this->fields;
  }

  public function getRevision() {
    if ($this->revision === false) {
      $fields = $this->getCommitFields();
      $revision_id = idx($fields, 'revisionID');
      if (!$revision_id) {
        $this->revision = null;
      } else {
        $this->revision = id(new DifferentialRevisionQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withIDs(array($revision_id))
          ->needReviewers(true)
          ->executeOne();
      }
    }

    return $this->revision;
  }

  public function getIsMergeCommit() {
    $repository = $this->getHookEngine()->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $parents = id(new DiffusionLowLevelParentsQuery())
          ->setRepository($repository)
          ->withIdentifier($this->getObject()->getRefNew())
          ->execute();

        return (count($parents) > 1);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // NOTE: For now, we ignore "svn:mergeinfo" at all levels. We might
        // change this some day, but it's not nearly as clear a signal as
        // ancestry is in Git/Mercurial.
        return false;
    }
  }

  public function getBranches() {
    return $this->getHookEngine()->loadBranches(
      $this->getObject()->getRefNew());
  }

  public function loadAffectedPackages() {
    if ($this->affectedPackages === null) {
      $packages = PhabricatorOwnersPackage::loadAffectedPackages(
        $this->getHookEngine()->getRepository(),
        $this->getDiffContent('name'));
      $this->affectedPackages = $packages;
    }

    return $this->affectedPackages;
  }


}
