<?php

final class PhabricatorAuthLinkMessageType
  extends PhabricatorAuthMessageType {

  const MESSAGEKEY = 'auth.link';

  public function getDisplayName() {
    return pht('Unlinked Account Instructions');
  }

  public function getShortDescription() {
    return pht(
      'Guidance shown after a user logs in with an email link and is '.
      'prompted to link an external account.');
  }

}
