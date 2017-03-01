<?php

final class PhabricatorPackagesCreatePublisherCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'packages.publisher.create';

  public function getCapabilityName() {
    return pht('Can Create Publishers');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create publishers.');
  }

}
