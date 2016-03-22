<?php

final class PhabricatorOAuth1SecretTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'oauth1:request:secret';

  public function getTokenTypeDisplayName() {
    return pht('OAuth1 Handshake Secret');
  }

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('OAuth1 Handshake Token');
  }

}
