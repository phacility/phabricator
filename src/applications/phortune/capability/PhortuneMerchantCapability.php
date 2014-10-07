<?php

final class PhortuneMerchantCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'phortune.merchant';

  public function getCapabilityName() {
    return pht('Can Create Merchants');
  }

  public function describeCapabilityRejection() {
    return pht(
      'You do not have permission to create Phortune merchant accounts.');
  }

}
