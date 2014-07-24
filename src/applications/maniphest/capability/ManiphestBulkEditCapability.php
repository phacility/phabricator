<?php

final class ManiphestBulkEditCapability extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.bulk';

  public function getCapabilityName() {
    return pht('Can Bulk Edit Tasks');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to bulk edit tasks.');
  }

}
