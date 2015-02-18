<?php

final class PhabricatorMailingListsManageCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'mailinglists.manage';

  public function getCapabilityName() {
    return pht('Can Manage Lists');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to manage mailing lists.');
  }

}
