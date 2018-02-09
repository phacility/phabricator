<?php

final class HeraldCreateWebhooksCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'herald.webhooks';

  public function getCapabilityName() {
    return pht('Can Create Webhooks');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create webhooks.');
  }

}
