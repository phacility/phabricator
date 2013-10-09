<?php

final class HeraldCapabilityCreateRules
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'herald.create';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Create Rules');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create new Herald rules.');
  }

}
