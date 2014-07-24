<?php

final class PhragmentCanCreateCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'phragment.create';

  public function getCapabilityName() {
    return pht('Can Create Fragments');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create fragments.');
  }

}
