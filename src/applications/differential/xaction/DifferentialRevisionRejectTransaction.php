<?php

final class DifferentialRevisionRejectTransaction
  extends DifferentialRevisionReviewTransaction {

  const TRANSACTIONTYPE = 'differential.revision.reject';
  const ACTIONKEY = 'reject';

  protected function getRevisionActionLabel() {
    return pht("Request Changes \xE2\x9C\x98");
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision) {
    return pht('This revision will be returned to the author for updates.');
  }

  public function getIcon() {
    return 'fa-times-circle-o';
  }

  public function getColor() {
    return 'red';
  }

  protected function getRevisionActionOrder() {
    return 600;
  }

  public function getActionName() {
    return pht('Requested Changes');
  }

  public function getCommandKeyword() {
    return 'request';
  }

  public function getCommandAliases() {
    return array(
      'reject',
    );
  }

  public function getCommandSummary() {
    return pht('Request changes to a revision.');
  }

  public function generateOldValue($object) {
    $actor = $this->getActor();
    return $this->isViewerFullyRejected($object, $actor);
  }

  public function applyExternalEffects($object, $value) {
    $status = DifferentialReviewerStatus::STATUS_REJECTED;
    $actor = $this->getActor();
    $this->applyReviewerEffect($object, $actor, $value, $status);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not request changes to this revision because it has '.
          'already been closed. You can only request changes to open '.
          'revisions.'));
    }

    if ($this->isViewerRevisionAuthor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not request changes to this revision because you are the '.
          'revision author. You can only request changes to revisions you do '.
          'not own.'));
    }

    if ($object->isDraft()) {
      throw new Exception(
        pht('You can not request changes to a draft revision.'));
    }

    if ($this->isViewerFullyRejected($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not request changes to this revision because you have '.
          'already requested changes.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s requested changes to this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s requested changes to %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
