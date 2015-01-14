<?php

final class PhabricatorOAuthServerClient
  extends PhabricatorOAuthServerDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

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

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'secret' => 'text32',
        'redirectURI' => 'text255',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'creatorPHID' => array(
          'columns' => array('creatorPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorOAuthServerClientPHIDType::TYPECONST);
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

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();

      $authorizations = id(new PhabricatorOAuthClientAuthorization())
        ->loadAllWhere('clientPHID = %s', $this->getPHID());
      foreach ($authorizations as $authorization) {
        $authorization->delete();
      }

      $tokens = id(new PhabricatorOAuthServerAccessToken())
        ->loadAllWhere('clientPHID = %s', $this->getPHID());
      foreach ($tokens as $token) {
        $token->delete();
      }

      $codes = id(new PhabricatorOAuthServerAuthorizationCode())
        ->loadAllWhere('clientPHID = %s', $this->getPHID());
      foreach ($codes as $code) {
        $code->delete();
      }

    $this->saveTransaction();

  }
}
