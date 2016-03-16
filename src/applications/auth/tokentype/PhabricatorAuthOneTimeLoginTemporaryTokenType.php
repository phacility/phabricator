<?php

final class PhabricatorAuthOneTimeLoginTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'login:onetime';

  public function getTokenTypeDisplayName() {
    return pht('One-Time Login');
  }

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('One-Time Login Token');
  }

  public function isTokenRevocable(PhabricatorAuthTemporaryToken $token) {
    return true;
  }

}
