<?php

final class PeopleCreateUsersCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'people.create.users';

  public function getCapabilityName() {
    return pht('Can Create (non-bot) Users');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create users.');
  }

}
