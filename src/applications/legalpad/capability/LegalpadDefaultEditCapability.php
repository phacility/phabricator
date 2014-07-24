<?php

final class LegalpadDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'legalpad.default.edit';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
