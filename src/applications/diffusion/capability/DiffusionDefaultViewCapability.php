<?php

final class DiffusionDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
