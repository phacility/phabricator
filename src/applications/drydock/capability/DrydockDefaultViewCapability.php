<?php

final class DrydockDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'drydock.default.view';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Blueprint View Policy');
  }

}
