<?php

final class LegalpadCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'legalpad.default.view';

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
