<?php

final class PhabricatorOwnersDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'owners.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
