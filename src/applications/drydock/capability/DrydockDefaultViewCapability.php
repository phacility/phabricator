<?php

final class DrydockDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'drydock.default.view';

  public function getCapabilityName() {
    return pht('Default Blueprint View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
