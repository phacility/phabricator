<?php

final class PhabricatorFileAccessTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'file:onetime';

  public function getTokenTypeDisplayName() {
    return pht('File Access');
  }

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('File Access Token');
  }

}
