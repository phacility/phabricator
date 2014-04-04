<?php

final class ManiphestCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.default.view';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
