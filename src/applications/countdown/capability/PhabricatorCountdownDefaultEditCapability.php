<?php

final class PhabricatorCountdownDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'countdown.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
