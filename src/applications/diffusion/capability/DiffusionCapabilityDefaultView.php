<?php

final class DiffusionCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.default.view';

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
