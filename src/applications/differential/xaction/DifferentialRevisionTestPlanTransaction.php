<?php

final class DifferentialRevisionTestPlanTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.testplan';
  const EDITKEY = 'testPlan';

  public function generateOldValue($object) {
    return $object->getTestPlan();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTestPlan($value);
  }

  public function getTitle() {
    return pht(
      '%s edited the test plan for this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the test plan for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO TEST PLAN');
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($this->getOldValue())
      ->setNewText($this->getNewValue());
  }

  public function newRemarkupChanges() {
    $changes = array();

    $changes[] = $this->newRemarkupChange()
      ->setOldValue($this->getOldValue())
      ->setNewValue($this->getNewValue());

    return $changes;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = $this->validateCommitMessageCorpusTransactions(
      $object,
      $xactions,
      pht('Test Plan'));

    $is_required = PhabricatorEnv::getEnvConfig(
      'differential.require-test-plan-field');

    if ($is_required) {
      if ($this->isEmptyTextTransaction($object->getTestPlan(), $xactions)) {
        $errors[] = $this->newRequiredError(
          pht(
            'You must provide a test plan. Describe the actions you '.
            'performed to verify the behavior of this change.'));
      }
    }

    return $errors;
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'testPlan';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

}
