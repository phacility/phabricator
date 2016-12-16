<?php

final class DifferentialTestPlanCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'testPlan';

  public function getFieldName() {
    return pht('Test Plan');
  }

  public function getFieldOrder() {
    return 3000;
  }

  public function getFieldAliases() {
    return array(
      'Testplan',
      'Tested',
      'Tests',
    );
  }

  public function isFieldEnabled() {
    return $this->isCustomFieldEnabled('differential:test-plan');
  }

  public function validateFieldValue($value) {
    $is_required = PhabricatorEnv::getEnvConfig(
      'differential.require-test-plan-field');

    if ($is_required && !strlen($value)) {
      $this->raiseValidationException(
        pht(
          'You must provide a test plan. Describe the actions you performed '.
          'to verify the behavior of this change.'));
    }
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    return $revision->getTestPlan();
  }

  public function getFieldTransactions($value) {
    return array(
      array(
        'type' => DifferentialRevisionTestPlanTransaction::EDITKEY,
        'value' => $value,
      ),
    );
  }

}
