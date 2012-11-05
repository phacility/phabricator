<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthServerClient
extends PhabricatorOAuthServerDAO {

  protected $id;
  protected $phid;
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

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_OASC);
  }

}
