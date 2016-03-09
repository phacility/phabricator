<?php

final class PhabricatorOwnersDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'owners.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
