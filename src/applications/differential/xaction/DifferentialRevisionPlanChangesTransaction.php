<?php

final class DifferentialRevisionPlanChangesTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.plan';
  const ACTIONKEY = 'plan-changes';

  protected function getRevisionActionLabel(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return pht('Plan Changes');
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return pht(
      'This revision will be removed from review queues until it is revised.');
  }

  public function getIcon() {
    return 'fa-headphones';
  }

  public function getColor() {
    return 'red';
  }

  protected function getRevisionActionOrder() {
    return 200;
  }

  public function getActionName() {
    return pht('Planned Changes');
  }

  public function getCommandKeyword() {
    return 'planchanges';
  }

  public function getCommandAliases() {
    return array(
      'rethink',
    );
  }

  public function getCommandSummary() {
    return pht('Plan changes to a revision.');
  }

  public function generateOldValue($object) {
    return $object->isChangePlanned();
  }

  public function applyInternalEffects($object, $value) {
    $status_planned = DifferentialRevisionStatus::CHANGES_PLANNED;
    $object->setModernRevisionStatus($status_planned);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($object->isDraft()) {

      // See PHI346. Until the "Draft" state fully unprototypes, allow drafts
      // to be moved to "changes planned" via the API. This preserves the
      // behavior of "arc diff --plan-changes". We still prevent this
      // transition from the web UI.
      // TODO: Remove this once drafts leave prototype.

      $editor = $this->getEditor();
      $type_web = PhabricatorWebContentSource::SOURCECONST;
      if ($editor->getContentSource()->getSource() == $type_web) {
        throw new Exception(
          pht('You can not plan changes to a draft revision.'));
      }
    }

    if ($object->isChangePlanned()) {
      throw new Exception(
        pht(
          'You can not request review of this revision because this '.
          'revision is already under review and the action would have '.
          'no effect.'));
    }

    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not plan changes to this this revision because it has '.
          'already been closed.'));
    }

    if (!$this->isViewerRevisionAuthor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not plan changes to this revision because you do not '.
          'own it. Only the author of a revision can plan changes to it.'));
    }
  }

  public function getTitle() {
    if ($this->isDraftDemotion()) {
      return pht(
        '%s returned this revision to the author for changes because remote '.
        'builds failed.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s planned changes to this revision.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    return pht(
      '%s planned changes to %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  private function isDraftDemotion() {
    return (bool)$this->getMetadataValue('draft.demote');
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'plan-changes';
  }

  public function getFieldValuesForConduit($object, $data) {
    return array();
  }

}
