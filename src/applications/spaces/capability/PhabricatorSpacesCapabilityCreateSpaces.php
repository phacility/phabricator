<?php

final class PhabricatorSpacesCapabilityCreateSpaces
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'spaces.create';

  public function getCapabilityName() {
    return pht('Can Create Spaces');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create spaces.');
  }

}
