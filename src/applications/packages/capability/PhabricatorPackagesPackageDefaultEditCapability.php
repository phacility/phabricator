<?php

final class PhabricatorPackagesPackageDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'packages.package.default.edit';

  public function getCapabilityName() {
    return pht('Default Package Edit Policy');
  }

}
