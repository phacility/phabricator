<?php

final class LegalpadDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'legalpad.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
