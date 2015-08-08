<?php

final class PonderQuestionDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'ponder.question.default.view';

  public function getCapabilityName() {
    return pht('Default Question View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
