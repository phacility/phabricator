<?php

final class PeopleDisableUsersCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'people.disable.users';

  public function getCapabilityName() {
    return pht('Can Disable Users');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to disable or enable users.');
  }

}
