<?php

final class PhabricatorChatLogChannel
  extends PhabricatorChatLogDAO
  implements PhabricatorPolicyInterface {

  protected $serviceName;
  protected $serviceType;
  protected $channelName;
  protected $viewPolicy;
  protected $editPolicy;

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->viewPolicy;
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->editPolicy;
        break;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}

