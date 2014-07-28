<?php

final class LegalpadDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'legalpad.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
