<?php

final class DifferentialRevisionReclaimTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.reclaim';
  const ACTIONKEY = 'reclaim';

  protected function getRevisionActionLabel(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return pht('Reclaim Revision');
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return pht('This revision will be reclaimed and reopened.');
  }

  public function getIcon() {
    return 'fa-bullhorn';
  }

  public function getColor() {
    return 'sky';
  }

  protected function getRevisionActionOrder() {
    return 600;
  }

  public function getActionName() {
    return pht('Reclaimed');
  }

  public function getCommandKeyword() {
    return 'reclaim';
  }

  public function getCommandAliases() {
    return array();
  }

  public function getCommandSummary() {
    return pht('Reclaim a revision.');
  }

  public function generateOldValue($object) {
    return !$object->isAbandoned();
  }

  public function applyInternalEffects($object, $value) {
    if ($object->getShouldBroadcast()) {
      $new_status = DifferentialRevisionStatus::NEEDS_REVIEW;
    } else {
      $new_status = DifferentialRevisionStatus::DRAFT;
    }
    $object->setModernRevisionStatus($new_status);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if (!$object->isAbandoned()) {
      throw new Exception(
        pht(
          'You can not reclaim this revision because it has not been '.
          'abandoned. Only abandoned revisions can be reclaimed.'));
    }

    if (!$this->isViewerRevisionAuthor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not reclaim this revision because you are not the '.
          'revision author. You can only reclaim revisions you own.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s reclaimed this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s reclaimed %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'reclaim';
  }

  public function getFieldValuesForConduit($object, $data) {
    return array();
  }

}
