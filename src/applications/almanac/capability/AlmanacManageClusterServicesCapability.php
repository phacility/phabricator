<?php

final class AlmanacManageClusterServicesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'almanac.cluster';

  public function getCapabilityName() {
    return pht('Can Manage Cluster Services');
  }

  public function describeCapabilityRejection() {
    return pht(
      'You do not have permission to manage Almanac cluster services.');
  }

}
