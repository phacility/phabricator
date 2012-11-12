<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthClientAuthorization
extends PhabricatorOAuthServerDAO {

  protected $id;
  protected $phid;
  protected $userPHID;
  protected $clientPHID;
  protected $scope;

  public function getEditURI() {
    return '/oauthserver/clientauthorization/edit/'.$this->getPHID().'/';
  }

  public function getDeleteURI() {
    return '/oauthserver/clientauthorization/delete/'.$this->getPHID().'/';
  }

  public function getScopeString() {
    $scope = $this->getScope();
    $scopes = array_keys($scope);
    sort($scopes);
    return implode(' ', $scopes);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'scope' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_OASA);
  }
}
