<?php

final class NuanceSourceDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'nuance.source.default.edit';

  public function getCapabilityName() {
    return pht('Default Source Edit Policy');
  }

}
