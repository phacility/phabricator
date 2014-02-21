<?php

final class DifferentialTestPlanField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:test-plan';
  }

  public function getFieldName() {
    return pht('Test Plan');
  }

  public function getFieldDescription() {
    return pht('Actions performed to verify the behavior of the change.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getTestPlan();
  }

  protected function writeValueToRevision(
    DifferentialRevision $revision,
    $value) {
    $revision->setTestPlan($value);
  }

  protected function isCoreFieldRequired() {
    return PhabricatorEnv::getEnvConfig('differential.require-test-plan-field');
  }

  public function canDisableField() {
    return true;
  }

  protected function getCoreFieldRequiredErrorString() {
    return pht(
      'You must provide a test plan: describe the actions you performed '.
      'to verify the behvaior of this change.');
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
      '%s updated the test plan for this revision.',
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
      '%s updated the test plan for %s.',
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

}
