<?php

final class DiffusionDefaultEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
