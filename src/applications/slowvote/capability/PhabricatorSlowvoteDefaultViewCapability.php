<?php

final class PhabricatorSlowvoteDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'slowvote.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
