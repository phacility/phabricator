<?php

final class AuthManageProvidersCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'auth.manage.providers';

  public function getCapabilityName() {
    return pht('Can Manage Auth Providers');
  }

  public function describeCapabilityRejection() {
    return pht(
      'You do not have permission to manage authentication providers.');
  }

}
