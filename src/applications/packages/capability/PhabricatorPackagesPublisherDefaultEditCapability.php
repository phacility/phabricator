<?php

final class PhabricatorPackagesPublisherDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'packages.publisher.default.edit';

  public function getCapabilityName() {
    return pht('Default Publisher Edit Policy');
  }

}
