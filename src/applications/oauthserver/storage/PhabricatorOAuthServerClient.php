<?php

final class PhabricatorOAuthServerClient
  extends PhabricatorOAuthServerDAO
  implements PhabricatorPolicyInterface {

  protected $secret;
  protected $name;
  protected $redirectURI;
  protected $creatorPHID;

  public function getEditURI() {
    return '/oauthserver/client/edit/'.$this->getPHID().'/';
  }

  public function getViewURI() {
    return '/oauthserver/client/view/'.$this->getPHID().'/';
  }

  public function getDeleteURI() {
    return '/oauthserver/client/delete/'.$this->getPHID().'/';
  }

  public static function initializeNewClient(PhabricatorUser $actor) {
    return id(new PhabricatorOAuthServerClient())
      ->setCreatorPHID($actor->getPHID())
      ->setSecret(Filesystem::readRandomCharacters(32));
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorOAuthServerPHIDTypeClient::TYPECONST);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::POLICY_USER;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        return ($viewer->getPHID() == $this->getCreatorPHID());
    }
    return false;
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht("Only an application's creator can edit it.");
    }
    return null;
  }

}
