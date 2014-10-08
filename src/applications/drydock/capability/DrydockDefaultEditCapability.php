<?php

final class DrydockDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'drydock.default.edit';

  public function getCapabilityName() {
    return pht('Default Blueprint Edit Policy');
  }

}
