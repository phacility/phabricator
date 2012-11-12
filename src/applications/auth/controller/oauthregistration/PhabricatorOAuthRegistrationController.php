<?php

abstract class PhabricatorOAuthRegistrationController
  extends PhabricatorAuthController {

  private $oauthProvider;
  private $oauthInfo;
  private $oauthState;

  final public function setOAuthInfo($info) {
    $this->oauthInfo = $info;
    return $this;
  }

  final public function getOAuthInfo() {
    return $this->oauthInfo;
  }

  final public function setOAuthProvider($provider) {
    $this->oauthProvider = $provider;
    return $this;
  }

  final public function getOAuthProvider() {
    return $this->oauthProvider;
  }

  final public function setOAuthState($state) {
    $this->oauthState = $state;
    return $this;
  }

  final public function getOAuthState() {
    return $this->oauthState;
  }

}
