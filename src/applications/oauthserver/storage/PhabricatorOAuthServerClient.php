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
  protected $isTrusted = 0;
  protected $viewPolicy;
  protected $editPolicy;

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
      ->setSecret(Filesystem::readRandomCharacters(32))
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy($actor->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'secret' => 'text32',
        'redirectURI' => 'text255',
        'isTrusted' => 'bool',
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
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
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
