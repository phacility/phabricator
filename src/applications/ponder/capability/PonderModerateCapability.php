<?php

final class PonderModerateCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'ponder.moderate';

  public function getCapabilityName() {
    return pht('Moderate Policy');
  }

}
