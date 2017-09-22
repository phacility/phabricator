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
    if (!$revision->getID()) {
      return null;
    }
    return $revision->getTestPlan();
  }

  public function canDisableField() {
    return true;
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

    return new PHUIRemarkupView($this->getViewer(), $this->getValue());
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    if (!$editor->isFirstBroadcast()) {
      return;
    }

    $test_plan = $this->getValue();
    if (!strlen(trim($test_plan))) {
      return;
    }

    $body->addRemarkupSection(pht('TEST PLAN'), $test_plan);
  }


}
