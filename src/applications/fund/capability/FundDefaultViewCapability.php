<?php

final class FundDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'fund.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
