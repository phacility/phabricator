<?php

final class LegalpadCreateDocumentsCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'legalpad.create';

  public function getCapabilityName() {
    return pht('Can Create Documents');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create new documents.');
  }

}
