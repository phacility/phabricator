<?php

final class PhabricatorOAuthServerCreateClientsCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'oauthserver.create';

  public function getCapabilityName() {
    return pht('Can Create OAuth Applications');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create OAuth applications.');
  }

}
