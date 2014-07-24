<?php

final class NuanceSourceDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'nuance.source.default.view';

  public function getCapabilityName() {
    return pht('Default Source View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
