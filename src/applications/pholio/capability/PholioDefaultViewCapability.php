<?php

final class PholioDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'pholio.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
