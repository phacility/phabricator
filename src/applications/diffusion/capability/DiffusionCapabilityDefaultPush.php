<?php

final class DiffusionCapabilityDefaultPush
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.default.push';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Push Policy');
  }

}
