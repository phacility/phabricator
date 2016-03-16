<?php

final class PhabricatorAuthTOTPKeyTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'mfa:totp:key';

  public function getTokenTypeDisplayName() {
    return pht('TOTP Synchronization');
  }

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('TOTP Sync Token');
  }

}
