<?php

final class PhabricatorOAuthServerCapabilityCreateClients
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'oauthserver.create';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Create OAuth Applications');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create OAuth applications.');
  }

}
