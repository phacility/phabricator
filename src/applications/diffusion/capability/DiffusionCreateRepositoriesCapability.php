<?php

final class DiffusionCreateRepositoriesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'diffusion.create';

  public function getCapabilityName() {
    return pht('Can Create Repositories');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create new repositories.');
  }

}
