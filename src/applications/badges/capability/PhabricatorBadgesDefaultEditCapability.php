<?php

final class PhabricatorBadgesDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'badges.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Badges');
  }

}
