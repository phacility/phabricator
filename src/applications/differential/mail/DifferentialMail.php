<?php

abstract class DifferentialMail extends PhabricatorMail {

  public static function newReplyHandlerForRevision(
    DifferentialRevision $revision) {
    $reply_handler = new DifferentialReplyHandler();
    $reply_handler->setMailReceiver($revision);
    return $reply_handler;
  }

}
