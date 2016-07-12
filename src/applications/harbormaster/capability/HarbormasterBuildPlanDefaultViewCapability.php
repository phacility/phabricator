<?php

final class HarbormasterBuildPlanDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'harbomaster.plan.default.view';

  public function getCapabilityName() {
    return pht('Default Build Plan View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
