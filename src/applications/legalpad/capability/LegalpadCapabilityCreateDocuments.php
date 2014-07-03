<?php

final class LegalpadCapabilityCreateDocuments
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'legalpad.create';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Create Documents');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create new documents.');
  }

}
