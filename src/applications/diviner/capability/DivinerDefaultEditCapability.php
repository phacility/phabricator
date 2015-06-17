<?php

final class DivinerDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diviner.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
