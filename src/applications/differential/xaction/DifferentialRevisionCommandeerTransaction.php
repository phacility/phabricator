<?php

final class DifferentialRevisionCommandeerTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.commandeer';
  const ACTIONKEY = 'commandeer';

  protected function getRevisionActionLabel(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return pht('Commandeer Revision');
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return pht('You will take control of this revision and become its author.');
  }

  public function getIcon() {
    return 'fa-flag';
  }

  public function getColor() {
    return 'sky';
  }

  protected function getRevisionActionOrder() {
    return 700;
  }

  public function getActionName() {
    return pht('Commandeered');
  }

  public function getCommandKeyword() {
    return 'commandeer';
  }

  public function getCommandAliases() {
    return array(
      'claim',
    );
  }

  public function getCommandSummary() {
    return pht('Commandeer a revision.');
  }

  public function generateOldValue($object) {
    return $object->getAuthorPHID();
  }

  public function generateNewValue($object, $value) {
    $actor = $this->getActor();
    return $actor->getPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAuthorPHID($value);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    // If a revision has already landed, we generally want to discourage
    // reopening and reusing it since this tends to create a big mess (users
    // should create a new revision instead). Thus, we stop you from
    // commandeering closed revisions.

    // See PHI985. If the revision was abandoned, there's no peril in allowing
    // the commandeer since the change (likely) never actually landed. So
    // it's okay to commandeer abandoned revisions.

    if ($object->isClosed() && !$object->isAbandoned()) {
      throw new Exception(
        pht(
          'You can not commandeer this revision because it has already '.
          'been closed. You can only commandeer open or abandoned '.
          'revisions.'));
    }

    if ($this->isViewerRevisionAuthor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not commandeer this revision because you are already '.
          'the author.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s commandeered this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s commandeered %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'commandeer';
  }

  public function getFieldValuesForConduit($object, $data) {
    return array();
  }

}
