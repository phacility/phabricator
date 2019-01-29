<?php

final class PhabricatorAuthMFASyncTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'mfa.sync';
  const DIGEST_KEY = 'mfa.sync';

  public function getTokenTypeDisplayName() {
    return pht('MFA Sync');
  }

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('MFA Sync Token');
  }

}
