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
  abstract protected function getRevisionActionLabel(
    DifferentialRevision $revision,
    PhabricatorUser $viewer);

  protected function validateOptionValue($object, $actor, array $value) {
    return null;
  }

  public function getCommandKeyword() {
    return null;
  }

  public function getCommandAliases() {
    return array();
  }

  public function getCommandSummary() {
    return null;
  }

  protected function getRevisionActionOrder() {
    return 1000;
  }

  public function getActionStrength() {
    return 300;
  }

  public function getRevisionActionOrderVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getRevisionActionOrder());
  }

  protected function getRevisionActionGroupKey() {
    return DifferentialRevisionEditEngine::ACTIONGROUP_REVISION;
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return null;
  }

  protected function getRevisionActionSubmitButtonText(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return null;
  }

  protected function getRevisionActionMetadata(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return array();
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

  protected function getActionOptions(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {
    return array(
      array(),
      array(),
    );
  }

  public function newEditField(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {

    // Actions in the "review" group, like "Accept Revision", do not require
    // that the actor be able to edit the revision.
    $group_review = DifferentialRevisionEditEngine::ACTIONGROUP_REVIEW;
    $is_review = ($this->getRevisionActionGroupKey() == $group_review);

    $field = id(new PhabricatorApplyEditField())
      ->setKey($this->getRevisionActionKey())
      ->setTransactionType($this->getTransactionTypeConstant())
      ->setCanApplyWithoutEditCapability($is_review)
      ->setValue(true);

    if ($this->isActionAvailable($revision, $viewer)) {
      $label = $this->getRevisionActionLabel($revision, $viewer);
      if ($label !== null) {
        $field->setCommentActionLabel($label);

        $description = $this->getRevisionActionDescription($revision, $viewer);
        $field->setActionDescription($description);

        $group_key = $this->getRevisionActionGroupKey();
        $field->setCommentActionGroupKey($group_key);

        $button_text = $this->getRevisionActionSubmitButtonText(
          $revision,
          $viewer);
        $field->setActionSubmitButtonText($button_text);

        // Currently, every revision action conflicts with every other
        // revision action: for example, you can not simultaneously Accept and
        // Reject a revision.

        // Under some configurations, some combinations of actions are sort of
        // technically permissible. For example, you could reasonably Reject
        // and Abandon a revision if "anyone can abandon anything" is enabled.

        // It's not clear that these combinations are actually useful, so just
        // keep things simple for now.
        $field->setActionConflictKey('revision.action');

        list($options, $value) = $this->getActionOptions($viewer, $revision);

        // Show the options if the user can select on behalf of two or more
        // reviewers, or can force-accept on behalf of one or more reviewers,
        // or can accept on behalf of a reviewer other than themselves (see
        // T12533).
        $can_multi = (count($options) > 1);
        $can_force = (count($value) < count($options));
        $not_self = (head_key($options) != $viewer->getPHID());

        if ($can_multi || $can_force || $not_self) {
          $field->setOptions($options);
          $field->setValue($value);
        }

        $metadata = $this->getRevisionActionMetadata($revision, $viewer);
        foreach ($metadata as $metadata_key => $metadata_value) {
          $field->setMetadataValue($metadata_key, $metadata_value);
        }
      }
    }

    return $field;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $actor = $this->getActor();

    $action_exception = null;
    foreach ($xactions as $xaction) {
      // If this is a draft demotion action, let it skip all the normal
      // validation. This is a little hacky and should perhaps move down
      // into the actual action implementations, but currently we can not
      // apply this rule in validateAction() because it doesn't operate on
      // the actual transaction.
      if ($xaction->getMetadataValue('draft.demote')) {
        continue;
      }

      try {
        $this->validateAction($object, $actor);
      } catch (Exception $ex) {
        $action_exception = $ex;
      }

      break;
    }

    foreach ($xactions as $xaction) {
      if ($action_exception) {
        $errors[] = $this->newInvalidError(
          $action_exception->getMessage(),
          $xaction);
        continue;
      }

      $new = $xaction->getNewValue();
      if (!is_array($new)) {
        continue;
      }

      try {
        $this->validateOptionValue($object, $actor, $new);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          $ex->getMessage(),
          $xaction);
      }
    }

    return $errors;
  }

}
