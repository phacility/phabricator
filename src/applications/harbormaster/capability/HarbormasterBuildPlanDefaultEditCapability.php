<?php

final class HarbormasterBuildPlanDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'harbormaster.plan.default.edit';

  public function getCapabilityName() {
    return pht('Default Build Plan Edit Policy');
  }

}
