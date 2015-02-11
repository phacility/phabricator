<?php

final class DifferentialTitleField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:title';
  }

  public function getFieldKeyForConduit() {
    return 'title';
  }

  public function getFieldName() {
    return pht('Title');
  }

  public function getFieldDescription() {
    return pht('Stores the revision title.');
  }

  public static function getDefaultTitle() {
    return pht('<<Replace this line with your Revision Title>>');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getTitle();
  }

  protected function writeValueToRevision(
    DifferentialRevision $revision,
    $value) {
    $revision->setTitle($value);
  }

  protected function getCoreFieldRequiredErrorString() {
    return pht('You must choose a title for this revision.');
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStr($this->getFieldKey()));
  }

  protected function isCoreFieldRequired() {
    return true;
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextAreaControl())
      ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
      ->setName($this->getFieldKey())
      ->setValue($this->getValue())
      ->setError($this->getFieldError())
      ->setLabel($this->getFieldName());
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if (strlen($old)) {
      return pht(
        '%s retitled this revision from "%s" to "%s".',
        $xaction->renderHandleLink($author_phid),
        $old,
        $new);
    } else {
      return pht(
        '%s created this revision.',
        $xaction->renderHandleLink($author_phid));
    }
  }

  public function getApplicationTransactionTitleForFeed(
    PhabricatorApplicationTransaction $xaction) {

    $object_phid = $xaction->getObjectPHID();
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if (strlen($old)) {
      return pht(
        '%s retitled %s, from "%s" to "%s".',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($object_phid),
        $old,
        $new);
    } else {
      return pht(
        '%s created %s.',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($object_phid));
    }
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function validateCommitMessageValue($value) {
    if (!strlen($value)) {
      throw new DifferentialFieldValidationException(
        pht(
          'You must provide a revision title in the first line '.
          'of your commit message.'));
    }

    if (preg_match('/^<<.*>>$/', $value)) {
      throw new DifferentialFieldValidationException(
        pht(
          'Replace the line "%s" with a human-readable revision title which '.
          'describes the changes you are making.',
          self::getDefaultTitle()));
    }
  }

}
