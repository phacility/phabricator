<?php

final class PholioCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'pholio.default.view';

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
