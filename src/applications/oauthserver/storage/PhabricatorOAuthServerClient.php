<?php

final class PhabricatorOAuthServerClient
  extends PhabricatorOAuthServerDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface {

  protected $secret;
  protected $name;
  protected $redirectURI;
  protected $creatorPHID;
  protected $isTrusted;
  protected $viewPolicy;
  protected $editPolicy;
  protected $isDisabled;

  public function getEditURI() {
    $id = $this->getID();
    return "/oauthserver/edit/{$id}/";
  }

  public function getViewURI() {
    $id = $this->getID();
    return "/oauthserver/client/view/{$id}/";
  }

  public static function initializeNewClient(PhabricatorUser $actor) {
    return id(new PhabricatorOAuthServerClient())
      ->setCreatorPHID($actor->getPHID())
      ->setSecret(Filesystem::readRandomCharacters(32))
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy($actor->getPHID())
      ->setIsDisabled(0)
      ->setIsTrusted(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'secret' => 'text32',
        'redirectURI' => 'text255',
        'isTrusted' => 'bool',
        'isDisabled' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
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


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorOAuthServerEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorOAuthServerTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
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
