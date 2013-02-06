<?php

/**
 * Placeholder to let people know that the bot has been renamed
 */
final class PhabricatorIRCBot extends PhabricatorDaemon {
  public function run() {
    throw new Exception(
      "This daemon has been deprecated, use `PhabricatorBot` instead.");
  }
}
