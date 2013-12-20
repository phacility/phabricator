<?php

final class HeraldPreCommitContentAdapter extends HeraldAdapter {

  private $log;
  private $hookEngine;
  private $changesets;
  private $commitRef;
  private $fields;
  private $revision = false;

  public function setPushLog(PhabricatorRepositoryPushLog $log) {
    $this->log = $log;
    return $this;
  }

  public function setHookEngine(DiffusionCommitHookEngine $engine) {
    $this->hookEngine = $engine;
    return $this;
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

  public function getObject() {
    return $this->log;
  }

  public function getAdapterContentName() {
    return pht('Commit Hook: Commit Content');
  }

  public function getFieldNameMap() {
    return array(
    ) + parent::getFieldNameMap();
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_BODY,
        self::FIELD_AUTHOR,
        self::FIELD_COMMITTER,
        self::FIELD_DIFF_FILE,
        self::FIELD_DIFF_CONTENT,
        self::FIELD_DIFF_ADDED_CONTENT,
        self::FIELD_DIFF_REMOVED_CONTENT,
        self::FIELD_REPOSITORY,
        self::FIELD_PUSHER,
        self::FIELD_PUSHER_PROJECTS,
        self::FIELD_DIFFERENTIAL_REVISION,
        self::FIELD_DIFFERENTIAL_ACCEPTED,
        self::FIELD_DIFFERENTIAL_REVIEWERS,
        self::FIELD_DIFFERENTIAL_CCS,
        self::FIELD_IS_MERGE_COMMIT,
        self::FIELD_RULE,
      ),
      parent::getFields());
  }

  public function getConditionsForField($field) {
    switch ($field) {
    }
    return parent::getConditionsForField($field);
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array(
          self::ACTION_BLOCK,
          self::ACTION_NOTHING
        );
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array(
          self::ACTION_NOTHING,
        );
    }
  }

  public function getValueTypeForFieldAndCondition($field, $condition) {
    return parent::getValueTypeForFieldAndCondition($field, $condition);
  }

  public function getPHID() {
    return $this->getObject()->getPHID();
  }

  public function getHeraldName() {
    return pht('Push Log');
  }

  public function getHeraldField($field) {
    $log = $this->getObject();
    switch ($field) {
      case self::FIELD_BODY:
        return $this->getCommitRef()->getMessage();
      case self::FIELD_AUTHOR:
        return $this->getAuthorPHID();
      case self::FIELD_COMMITTER:
        return $this->getCommitterPHID();
      case self::FIELD_DIFF_FILE:
        return $this->getDiffContent('name');
      case self::FIELD_DIFF_CONTENT:
        return $this->getDiffContent('*');
      case self::FIELD_DIFF_ADDED_CONTENT:
        return $this->getDiffContent('+');
      case self::FIELD_DIFF_REMOVED_CONTENT:
        return $this->getDiffContent('-');
      case self::FIELD_REPOSITORY:
        return $this->hookEngine->getRepository()->getPHID();
      case self::FIELD_PUSHER:
        return $this->hookEngine->getViewer()->getPHID();
      case self::FIELD_PUSHER_PROJECTS:
        return $this->hookEngine->loadViewerProjectPHIDsForHerald();
      case self::FIELD_DIFFERENTIAL_REVISION:
        $revision = $this->getRevision();
        if (!$revision) {
          return null;
        }
        return $revision->getPHID();
      case self::FIELD_DIFFERENTIAL_ACCEPTED:
        $revision = $this->getRevision();
        if (!$revision) {
          return null;
        }
        $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
        if ($revision->getStatus() != $status_accepted) {
          return null;
        }
        return $revision->getPHID();
      case self::FIELD_DIFFERENTIAL_REVIEWERS:
        $revision = $this->getRevision();
        if (!$revision) {
          return array();
        }
        return $revision->getReviewers();
      case self::FIELD_DIFFERENTIAL_CCS:
        $revision = $this->getRevision();
        if (!$revision) {
          return array();
        }
        return $revision->getCCPHIDs();
      case self::FIELD_IS_MERGE_COMMIT:
        return $this->getIsMergeCommit();
    }

    return parent::getHeraldField($field);
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case self::ACTION_NOTHING:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Did nothing.'));
          break;
        case self::ACTION_BLOCK:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Blocked push.'));
          break;
        default:
          throw new Exception(pht('No rules to handle action "%s"!', $action));
      }
    }

    return $result;
  }

  private function getDiffContent($type) {
    if ($this->changesets === null) {
      try {
        $this->changesets = $this->hookEngine->loadChangesetsForCommit(
          $this->log->getRefNew());
      } catch (Exception $ex) {
        $this->changesets = $ex;
      }
    }

    if ($this->changesets instanceof Exception) {
      $ex_class = get_class($this->changesets);
      $ex_message = $this->changesets->getmessage();
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

  private function getCommitRef() {
    if ($this->commitRef === null) {
      $this->commitRef = $this->hookEngine->loadCommitRefForCommit(
        $this->log->getRefNew());
    }
    return $this->commitRef;
  }

  private function getAuthorPHID() {
    $repository = $this->hookEngine->getRepository();
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
        return $this->hookEngine->getViewer()->getPHID();
    }
  }

  private function getCommitterPHID() {
    $repository = $this->hookEngine->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        // Here, if there's no committer, we're going to return the author
        // instead.
        $ref = $this->getCommitRef();
        $committer = $ref->getCommitter();
        if (!strlen($committer)) {
          return $this->getAuthorPHID();
        }
        $phid = $this->lookupUser($committer);
        if (!$phid) {
          return $this->getAuthorPHID();
        }
        return $phid;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // In Subversion, the pusher is always the committer.
        return $this->hookEngine->getViewer()->getPHID();
    }
  }

  private function lookupUser($author) {
    return id(new DiffusionResolveUserQuery())
      ->withName($author)
      ->execute();
  }

  private function getCommitFields() {
    if ($this->fields === null) {
      $this->fields = id(new DiffusionLowLevelCommitFieldsQuery())
        ->setRepository($this->hookEngine->getRepository())
        ->withCommitRef($this->getCommitRef())
        ->execute();
    }
    return $this->fields;
  }

  private function getRevision() {
    if ($this->revision === false) {
      $fields = $this->getCommitFields();
      $revision_id = idx($fields, 'revisionID');
      if (!$revision_id) {
        $this->revision = null;
      } else {
        $this->revision = id(new DifferentialRevisionQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withIDs(array($revision_id))
          ->needRelationships(true)
          ->executeOne();
      }
    }

    return $this->revision;
  }

  private function getIsMergeCommit() {
    $repository = $this->hookEngine->getRepository();
    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $parents = id(new DiffusionLowLevelParentsQuery())
          ->setRepository($repository)
          ->withIdentifier($this->log->getRefNew())
          ->execute();

        return (count($parents) > 1);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // NOTE: For now, we ignore "svn:mergeinfo" at all levels. We might
        // change this some day, but it's not nearly as clear a signal as
        // ancestry is in Git/Mercurial.
        return false;
    }
  }

}
