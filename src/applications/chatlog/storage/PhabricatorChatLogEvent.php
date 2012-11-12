<?php

final class PhabricatorChatLogEvent
  extends PhabricatorChatLogDAO
  implements PhabricatorPolicyInterface {

  protected $channel;
  protected $epoch;
  protected $author;
  protected $type;
  protected $message;
  protected $loggedByPHID;

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // TODO: This is sort of silly and mostly just so that we can use
    // CursorPagedPolicyAwareQuery; once we implement Channel objects we should
    // just delegate policy to them.
    return PhabricatorPolicies::POLICY_PUBLIC;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
