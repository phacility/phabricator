<?php

final class NuanceCapabilitySourceDefaultEdit
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'nuance.source.default.edit';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Default Source Edit Policy');
  }

}
