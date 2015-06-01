<?php

final class PhabricatorSpacesCapabilityDefaultView
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'spaces.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
