<?php

final class PassphraseDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'passphrase.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
