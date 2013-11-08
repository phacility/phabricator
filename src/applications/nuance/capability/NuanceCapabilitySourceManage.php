<?php

final class NuanceCapabilitySourceManage
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'nuance.source.manage';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Manage Sources');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to manage sources.');
  }

}
