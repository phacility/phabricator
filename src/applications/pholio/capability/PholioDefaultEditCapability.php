<?php

final class PholioDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'pholio.default.edit';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
