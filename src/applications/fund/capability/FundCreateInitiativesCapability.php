<?php

final class FundCreateInitiativesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'fund.create';

  public function getCapabilityName() {
    return pht('Can Create Initiatives');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create Fund initiatives.');
  }

}
