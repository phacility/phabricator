<?php

final class HarbormasterBuildPlanDefaultViewCapability
  extends PhabricatorPolicyCapability {

  // TODO: This is misspelled! See T13005.
  const CAPABILITY = 'harbomaster.plan.default.view';

  public function getCapabilityName() {
    return pht('Default Build Plan View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
