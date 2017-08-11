<?php

final class DifferentialRevisionRequestReviewTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.request';
  const ACTIONKEY = 'request-review';

  protected function getRevisionActionLabel() {
    return pht('Request Review');
  }

  protected function getRevisionActionDescription() {
    return pht('This revision will be returned to reviewers for feedback.');
  }

  public function getColor() {
    return 'sky';
  }

  protected function getRevisionActionOrder() {
    return 200;
  }

  public function getActionName() {
    return pht('Requested Review');
  }

  public function generateOldValue($object) {
    return $object->isNeedsReview();
  }

  public function applyInternalEffects($object, $value) {
    $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
    $object->setStatus($status_review);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($object->isNeedsReview()) {
      throw new Exception(
        pht(
          'You can not request review of this revision because this '.
          'revision is already under review and the action would have '.
          'no effect.'));
    }

    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not request review of this revision because it has '.
          'already been closed. You can only request review of open '.
          'revisions.'));
    }

    if (!$this->isViewerRevisionAuthor($object, $viewer)) {
      throw new Exception(
        pht(
          'You can not request review of this revision because you are not '.
          'the author of the revision.'));
    }
  }

  public function getTitle() {
    return pht(
      '%s requested review of this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s requested review of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
