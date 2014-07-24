<?php

final class DiffusionDefaultPushCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.default.push';

  public function getCapabilityName() {
    return pht('Default Push Policy');
  }

}
