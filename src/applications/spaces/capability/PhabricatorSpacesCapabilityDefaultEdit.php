<?php

final class PhabricatorSpacesCapabilityDefaultEdit
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'spaces.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
