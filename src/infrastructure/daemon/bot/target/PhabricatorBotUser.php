<?php

/**
 * Represents an individual user.
 */
final class PhabricatorBotUser extends PhabricatorBotTarget {

  public function isPublic() {
    return false;
  }

}
