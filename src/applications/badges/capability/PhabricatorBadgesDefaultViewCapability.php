<?php

final class PhabricatorBadgesDefaultViewCapability extends
  PhabricatorPolicyCapability {

  const CAPABILITY = 'badges.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
