<?php

final class PhabricatorChatLogEvent
  extends PhabricatorChatLogDAO
  implements PhabricatorPolicyInterface {

  protected $channelID;
  protected $epoch;
  protected $author;
  protected $type;
  protected $message;
  protected $loggedByPHID;

  private $channel = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function attachChannel(PhabricatorChatLogChannel $channel) {
    $this->channel = $channel;
    return $this;
  }

  public function getChannel() {
    return $this->assertAttached($this->channel);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getChannel()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getChannel()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
