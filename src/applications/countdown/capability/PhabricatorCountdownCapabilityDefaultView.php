<?php

final class PhabricatorCountdownCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'countdown.default.view';

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
