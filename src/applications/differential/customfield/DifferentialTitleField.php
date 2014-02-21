<?php

final class DifferentialTitleField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:title';
  }

  public function getFieldName() {
    return pht('Title');
  }

  public function getFieldDescription() {
    return pht('Stores the revision title.');
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

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    $transaction = null;
    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      if (!strlen($value)) {
        $error = new PhabricatorApplicationTransactionValidationError(
          $type,
          pht('Required'),
          pht('You must choose a title for this revision.'),
          $xaction);
        $error->setIsMissingFieldError(true);
        $errors[] = $error;
        $this->setFieldError(pht('Required'));
      }
    }
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStr($this->getFieldKey()));
  }

  public function renderEditControl() {
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
    PhabricatorApplicationTransaction $xaction,
    PhabricatorFeedStory $story) {

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

}
