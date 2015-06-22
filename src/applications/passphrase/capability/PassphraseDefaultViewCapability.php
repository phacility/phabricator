<?php

final class PassphraseDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'passphrase.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
