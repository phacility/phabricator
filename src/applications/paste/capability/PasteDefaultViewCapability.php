<?php

final class PasteDefaultViewCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'paste.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
