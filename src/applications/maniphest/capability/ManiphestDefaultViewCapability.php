<?php

final class ManiphestDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
