<?php

final class PhabricatorPhurlURLCreateCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'phurl.url.create';

  public function getCapabilityName() {
    return pht('Can Create Phurl URLs');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create a Phurl URL.');
  }

}
