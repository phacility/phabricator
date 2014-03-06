<?php

final class DifferentialSummaryField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:summary';
  }

  public function getFieldName() {
    return pht('Summary');
  }

  public function getFieldDescription() {
    return pht('Stores a summary of the revision.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getSummary();
  }

  protected function writeValueToRevision(
    DifferentialRevision $revision,
    $value) {
    $revision->setSummary($value);
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStr($this->getFieldKey()));
  }

  public function renderEditControl(array $handles) {
    return id(new PhabricatorRemarkupControl())
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

    return pht(
      '%s updated the summary for this revision.',
      $xaction->renderHandleLink($author_phid));
  }

  public function getApplicationTransactionTitleForFeed(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorFeedStory $story) {

    $object_phid = $xaction->getObjectPHID();
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    return pht(
      '%s updated the summary for %s.',
      $xaction->renderHandleLink($author_phid),
      $xaction->renderHandleLink($object_phid));
  }

  public function getApplicationTransactionHasChangeDetails(
    PhabricatorApplicationTransaction $xaction) {
    return true;
  }

  public function getApplicationTransactionChangeDetails(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorUser $viewer) {
    return $xaction->renderTextCorpusChangeDetails(
      $viewer,
      $xaction->getOldValue(),
      $xaction->getNewValue());
  }

  public function shouldAppearInGlobalSearch() {
    return true;
  }

  public function updateAbstractDocument(
    PhabricatorSearchAbstractDocument $document) {
    if (strlen($this->getValue())) {
      $document->addField('body', $this->getValue());
    }
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

  public function getIconForPropertyView() {
    return PHUIPropertyListView::ICON_SUMMARY;
  }

  public function renderPropertyViewValue(array $handles) {
    if (!strlen($this->getValue())) {
      return null;
    }

    return PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())
        ->setPreserveLinebreaks(true)
        ->setContent($this->getValue()),
      'default',
      $this->getViewer());
  }

  public function getApplicationTransactionRemarkupBlocks(
    PhabricatorApplicationTransaction $xaction) {
    return array($xaction->getNewValue());
  }

}
