<?php

final class NuanceCapabilitySourceDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'nuance.source.default.view';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Source View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
