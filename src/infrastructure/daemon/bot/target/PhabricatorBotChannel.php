<?php

/**
 * Represents a group/public space, like an IRC channel or a Campfire room.
 */
final class PhabricatorBotChannel extends PhabricatorBotTarget {

  public function isPublic() {
    return true;
  }

}
