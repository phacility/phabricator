<?php

final class PhabricatorCalendarEventDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'calendar.event.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
