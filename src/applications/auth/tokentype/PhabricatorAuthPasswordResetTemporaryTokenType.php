<?php

final class PhabricatorAuthPasswordResetTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'login:password';

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('Password Reset Token');
  }

  public function isTokenRevocable(PhabricatorAuthTemporaryToken $token) {
    return true;
  }

}
