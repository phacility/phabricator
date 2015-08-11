<?php

final class PonderDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'ponder.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
