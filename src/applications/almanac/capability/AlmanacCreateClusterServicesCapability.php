<?php

final class AlmanacCreateClusterServicesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'almanac.cluster';

  public function getCapabilityName() {
    return pht('Can Create Cluster Services');
  }

  public function describeCapabilityRejection() {
    return pht(
      'You do not have permission to create Almanac cluster services.');
  }

}
