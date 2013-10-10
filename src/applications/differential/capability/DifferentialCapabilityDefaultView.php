<?php

final class DifferentialCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'differential.default.view';

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
