<?php

final class DiffusionCapabilityPush
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.push';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Push');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to push to this repository.');
  }

}
