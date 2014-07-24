<?php

final class ManiphestDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.default.edit';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
