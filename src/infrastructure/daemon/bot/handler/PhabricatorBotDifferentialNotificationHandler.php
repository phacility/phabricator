<?php

/**
 * @deprecated
 */
final class PhabricatorBotDifferentialNotificationHandler
  extends PhabricatorBotHandler {

  public function receiveMessage(PhabricatorBotMessage $message) {
    static $notified;
    if (!$notified) {
      phlog(
        'PhabricatorBotDifferentialNotificationHandler is deprecated, use '.
        'PhabricatorBotFeedNotificationHandler instead.');
      $notified = true;
    }
  }

}
