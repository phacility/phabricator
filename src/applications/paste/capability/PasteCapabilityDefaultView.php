<?php

final class PasteCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'paste.default.view';

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
