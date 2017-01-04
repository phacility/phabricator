<?php

final class DifferentialRevisionResignTransaction
  extends DifferentialRevisionReviewTransaction {

  const TRANSACTIONTYPE = 'differential.revision.resign';
  const ACTIONKEY = 'resign';

  protected function getRevisionActionLabel() {
    return pht('Resign as Reviewer');
  }

  protected function getRevisionActionDescription() {
    return pht('You will resign as a reviewer for this change.');
  }

  public function getIcon() {
    return 'fa-flag';
  }

  public function getColor() {
    return 'orange';
  }

  protected function getRevisionActionOrder() {
    return 700;
  }

  public function getCommandKeyword() {
    return 'resign';
  }

  public function getCommandAliases() {
    return array();
  }

  public function getCommandSummary() {
    return pht('Resign from a revision.');
  }

  public function generateOldValue($object) {
    $actor = $this->getActor();
    return !$this->isViewerAnyReviewer($object, $actor);
  }

  public function applyExternalEffects($object, $value) {
    $status = DifferentialReviewerStatus::STATUS_RESIGNED;
    $actor = $this->getActor();
    $this->applyReviewerEffect($object, $actor, $value, $status);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not resign from this revision because it has already '.
          'been closed. You can only resign from open revisions.'));
    }

    if (!$this->isViewerAnyReviewer($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not resign from this revision because you are not a '.
          'reviewer. You can only resign from revisions where you are a '.
          'reviewer.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s resigned from this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s resigned from %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
