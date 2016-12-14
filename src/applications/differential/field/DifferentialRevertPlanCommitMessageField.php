<?php

final class DifferentialRevertPlanCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'revertPlan';

  public function getFieldName() {
    return pht('Revert Plan');
  }

  public function isFieldEnabled() {
    return $this->isCustomFieldEnabled('phabricator:revert-plan');
  }

}
