<?php

final class PhabricatorAuthChangeUsernameMessageType
  extends PhabricatorAuthMessageType {

  const MESSAGEKEY = 'user.edit.username';

  public function getDisplayName() {
    return pht('Username Change Instructions');
  }

  public function getShortDescription() {
    return pht(
      'Guidance in the "Change Username" dialog for requesting a '.
      'username change.');
  }

  public function getFullDescription() {
    return pht(
      'When users click the "Change Username" action on their profile pages '.
      'but do not have the required permissions, they will be presented with '.
      'a message explaining that they are not authorized to make the edit.'.
      "\n\n".
      'You can optionally provide additional instructions here to help users '.
      'request a username change, if there is someone specific they should '.
      'contact or a particular workflow they should use.');
  }

}
