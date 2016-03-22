<?php

final class PhabricatorAuthPasswordResetTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'login:password';

  public function getTokenTypeDisplayName() {
    return pht('Password Reset');
  }

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('Password Reset Token');
  }

  public function isTokenRevocable(PhabricatorAuthTemporaryToken $token) {
    return true;
  }

}
