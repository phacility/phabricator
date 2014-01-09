<?php

final class DrydockCapabilityDefaultEdit
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'drydock.default.edit';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Blueprint Edit Policy');
  }

}
