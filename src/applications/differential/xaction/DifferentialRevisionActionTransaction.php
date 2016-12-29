<?php

abstract class DifferentialRevisionActionTransaction
  extends DifferentialRevisionTransactionType {

  final public function getRevisionActionKey() {
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
  abstract protected function getRevisionActionLabel();

  protected function getRevisionActionOrder() {
    return 1000;
  }

  public function getRevisionActionOrderVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getRevisionActionOrder());
  }

  protected function getRevisionActionGroupKey() {
    return DifferentialRevisionEditEngine::ACTIONGROUP_REVISION;
  }

  protected function getRevisionActionDescription() {
    return null;
  }

  public static function loadAllActions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getRevisionActionKey')
      ->execute();
  }

  protected function isViewerRevisionAuthor(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {

    if (!$viewer->getPHID()) {
      return false;
    }

    return ($viewer->getPHID() === $revision->getAuthorPHID());
  }

  public function newEditField(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {

    $field = id(new PhabricatorApplyEditField())
      ->setKey($this->getRevisionActionKey())
      ->setTransactionType($this->getTransactionTypeConstant())
      ->setValue(true);

    if ($this->isActionAvailable($revision, $viewer)) {
      $label = $this->getRevisionActionLabel();
      if ($label !== null) {
        $field->setCommentActionLabel($label);

        $description = $this->getRevisionActionDescription();
        $field->setActionDescription($description);

        $group_key = $this->getRevisionActionGroupKey();
        $field->setCommentActionGroupKey($group_key);
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
