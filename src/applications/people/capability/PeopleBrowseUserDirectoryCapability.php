<?php

final class PeopleBrowseUserDirectoryCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'people.browse';

  public function getCapabilityName() {
    return pht('Can Browse User Directory');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to browse the user directory.');
  }

}
