<?php

final class DrydockCreateBlueprintsCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'drydock.blueprint.create';

  public function getCapabilityName() {
    return pht('Can Create Blueprints');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create Drydock blueprints.');
  }

}
