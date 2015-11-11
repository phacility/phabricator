<?php

final class HarbormasterCreatePlansCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'harbormaster.plans';

  public function getCapabilityName() {
    return pht('Can Create Build Plans');
  }

  public function describeCapabilityRejection() {
    return pht(
      'You do not have permission to create Harbormaster build plans.');
  }

}
