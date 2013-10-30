<?php

final class DiffusionCapabilityDefaultEdit
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.default.edit';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
