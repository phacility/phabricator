<?php

final class PhabricatorChatLogChannel
  extends PhabricatorChatLogDAO
  implements PhabricatorPolicyInterface {

  protected $serviceName;
  protected $serviceType;
  protected $channelName;
  protected $viewPolicy;
  protected $editPolicy;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'serviceName' => 'text64',
        'serviceType' => 'text32',
        'channelName' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_channel' => array(
          'columns' => array('channelName', 'serviceType', 'serviceName'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

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

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
