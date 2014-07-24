<?php

final class HarbormasterManagePlansCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'harbormaster.plans';

  public function getCapabilityName() {
    return pht('Can Manage Build Plans');
  }

  public function describeCapabilityRejection() {
    return pht(
      'You do not have permission to manage Harbormaster build plans.');
  }

}
