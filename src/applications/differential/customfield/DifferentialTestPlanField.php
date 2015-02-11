<?php

final class DifferentialTestPlanField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:test-plan';
  }

  public function getFieldKeyForConduit() {
    return 'testPlan';
  }

  public function getFieldName() {
    return pht('Test Plan');
  }

  public function getFieldDescription() {
    return pht('Actions performed to verify the behavior of the change.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    if (!$revision->getID()) {
      return null;
    }
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
      'You must provide a test plan. Describe the actions you performed '.
      'to verify the behavior of this change.');
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStr($this->getFieldKey()));
  }

  public function renderEditControl(array $handles) {
    return id(new PhabricatorRemarkupControl())
      ->setUser($this->getViewer())
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
    PhabricatorApplicationTransaction $xaction) {

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

  public function shouldHideInApplicationTransactions(
    PhabricatorApplicationTransaction $xaction) {
    return ($xaction->getOldValue() === null);
  }

  public function shouldAppearInGlobalSearch() {
    return true;
  }

  public function updateAbstractDocument(
    PhabricatorSearchAbstractDocument $document) {
    if (strlen($this->getValue())) {
      $document->addField('plan', $this->getValue());
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
    return PHUIPropertyListView::ICON_TESTPLAN;
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

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function shouldAppearInCommitMessageTemplate() {
    return true;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function getCommitMessageLabels() {
    return array(
      'Test Plan',
      'Testplan',
      'Tested',
      'Tests',
    );
  }

  public function validateCommitMessageValue($value) {
    if (!strlen($value) && $this->isCoreFieldRequired()) {
      throw new DifferentialFieldValidationException(
        $this->getCoreFieldRequiredErrorString());
    }
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    if (!$editor->getIsNewObject()) {
      return;
    }

    $test_plan = $this->getValue();
    if (!strlen(trim($test_plan))) {
      return;
    }

    $body->addTextSection(pht('TEST PLAN'), $test_plan);
  }


}
