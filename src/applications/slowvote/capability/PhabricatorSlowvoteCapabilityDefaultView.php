<?php

final class PhabricatorSlowvoteCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'slowvote.default.view';

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
