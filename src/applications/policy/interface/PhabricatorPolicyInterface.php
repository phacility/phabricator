<?php

interface PhabricatorPolicyInterface {

  public function getCapabilities();
  public function getPolicy($capability);
  public function hasAutomaticCapability($capability, PhabricatorUser $viewer);

}
