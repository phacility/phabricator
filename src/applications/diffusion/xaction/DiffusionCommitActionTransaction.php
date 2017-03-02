<?php

abstract class DiffusionCommitActionTransaction
  extends DiffusionCommitTransactionType {

  final public function getCommitActionKey() {
    return $this->getPhobjectClassConstant('ACTIONKEY', 32);
  }

  public function isActionAvailable($object, PhabricatorUser $viewer) {
    try {
      $this->validateAction($object, $viewer);
      return true;
    } catch (Exception $ex) {
      return false;
    }
  }

  abstract protected function validateAction($object, PhabricatorUser $viewer);
  abstract protected function getCommitActionLabel();

  public function getCommandKeyword() {
    return null;
  }

  public function getCommandAliases() {
    return array();
  }

  public function getCommandSummary() {
    return null;
  }

  protected function getCommitActionOrder() {
    return 1000;
  }

  public function getCommitActionOrderVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getCommitActionOrder());
  }

  protected function getCommitActionGroupKey() {
    return DiffusionCommitEditEngine::ACTIONGROUP_COMMIT;
  }

  protected function getCommitActionDescription() {
    return null;
  }

  public static function loadAllActions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getCommitActionKey')
      ->execute();
  }

  protected function isViewerCommitAuthor(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {

    if (!$viewer->getPHID()) {
      return false;
    }

    return ($viewer->getPHID() === $commit->getAuthorPHID());
  }

  public function newEditField(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {

    // Actions in the "audit" group, like "Accept Commit", do not require
    // that the actor be able to edit the commit.
    $group_audit = DiffusionCommitEditEngine::ACTIONGROUP_AUDIT;
    $is_audit = ($this->getCommitActionGroupKey() == $group_audit);

    $field = id(new PhabricatorApplyEditField())
      ->setKey($this->getCommitActionKey())
      ->setTransactionType($this->getTransactionTypeConstant())
      ->setCanApplyWithoutEditCapability($is_audit)
      ->setValue(true);

    if ($this->isActionAvailable($commit, $viewer)) {
      $label = $this->getCommitActionLabel();
      if ($label !== null) {
        $field->setCommentActionLabel($label);

        $description = $this->getCommitActionDescription();
        $field->setActionDescription($description);

        $group_key = $this->getCommitActionGroupKey();
        $field->setCommentActionGroupKey($group_key);

        $field->setActionConflictKey('commit.action');
      }
    }

    return $field;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $actor = $this->getActor();

    $action_exception = null;
    try {
      $this->validateAction($object, $actor);
    } catch (Exception $ex) {
      $action_exception = $ex;
    }

    foreach ($xactions as $xaction) {
      if ($action_exception) {
        $errors[] = $this->newInvalidError(
          $action_exception->getMessage(),
          $xaction);
      }
    }

    return $errors;
  }

}
