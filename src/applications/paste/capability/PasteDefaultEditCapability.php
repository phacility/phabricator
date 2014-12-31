<?php

final class PasteDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'paste.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
