<?php

final class DifferentialRevisionRequestReviewTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.request';
  const ACTIONKEY = 'request-review';

  const SOURCE_HARBORMASTER = 'harbormaster';
  const SOURCE_AUTHOR = 'author';
  const SOURCE_VIEWER = 'viewer';

  protected function getRevisionActionLabel(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {

    // See PHI1810. Allow non-authors to "Request Review" on draft revisions
    // to promote them out of the draft state. This smoothes over the workflow
    // where an author asks for review of an urgent change but has not used
    // "Request Review" to skip builds.

    if ($revision->isDraft()) {
      if (!$this->isViewerRevisionAuthor($revision, $viewer)) {
        return pht('Begin Review Now');
      }
    }

    return pht('Request Review');
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    if ($revision->isDraft()) {
      if (!$this->isViewerRevisionAuthor($revision, $viewer)) {
        return pht(
          'This revision will be moved out of the draft state so you can '.
          'review it immediately.');
      } else {
        return pht(
          'This revision will be submitted to reviewers for feedback.');
      }
    } else {
      return pht('This revision will be returned to reviewers for feedback.');
    }
  }

  protected function getRevisionActionMetadata(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    $map = array();

    if ($revision->isDraft()) {
      $action_source = $this->getActorSourceType(
        $revision,
        $viewer);
      $map['promotion.source'] = $action_source;
    }

    return $map;
  }

  protected function getRevisionActionSubmitButtonText(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {

    // See PHI975. When the action stack will promote the revision out of
    // draft, change the button text from "Submit Quietly".
    if ($revision->isDraft()) {
      return pht('Publish Revision');
    }

    return null;
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
    $status_review = DifferentialRevisionStatus::NEEDS_REVIEW;
    $object
      ->setModernRevisionStatus($status_review)
      ->setShouldBroadcast(true);
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

    $this->getActorSourceType($object, $viewer);
  }

  public function getTitle() {
    $source = $this->getDraftPromotionSource();

    switch ($source) {
      case self::SOURCE_HARBORMASTER:
      case self::SOURCE_VIEWER:
      case self::SOURCE_AUTHOR:
        return pht(
          '%s published this revision for review.',
          $this->renderAuthor());
      default:
        return pht(
          '%s requested review of this revision.',
          $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $source = $this->getDraftPromotionSource();

    switch ($source) {
      case self::SOURCE_HARBORMASTER:
      case self::SOURCE_VIEWER:
      case self::SOURCE_AUTHOR:
        return pht(
          '%s published %s for review.',
          $this->renderAuthor(),
          $this->renderObject());
      default:
        return pht(
          '%s requested review of %s.',
          $this->renderAuthor(),
          $this->renderObject());
    }
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'request-review';
  }

  public function getFieldValuesForConduit($object, $data) {
    return array();
  }

  private function getDraftPromotionSource() {
    return $this->getMetadataValue('promotion.source');
  }

  private function getActorSourceType(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {

    $is_harbormaster = $viewer->isOmnipotent();
    $is_author = $this->isViewerRevisionAuthor($revision, $viewer);
    $is_draft = $revision->isDraft();

    if ($is_harbormaster) {
      // When revisions automatically promote out of "Draft" after builds
      // finish, the viewer may be acting as the Harbormaster application.
      $source = self::SOURCE_HARBORMASTER;
    } else if ($is_author) {
      $source = self::SOURCE_AUTHOR;
    } else if ($is_draft) {
      // Non-authors are allowed to "Request Review" on draft revisions, to
      // force them into review immediately.
      $source = self::SOURCE_VIEWER;
    } else {
      throw new Exception(
        pht(
          'You can not request review of this revision because you are not '.
          'the author of the revision and it is not currently a draft.'));
    }

    return $source;
  }

}
