<?php

abstract class DifferentialMail extends PhabricatorMail {

  public static function newReplyHandlerForRevision(
    DifferentialRevision $revision) {

    $reply_handler = PhabricatorEnv::newObjectFromConfig(
      'metamta.differential.reply-handler');
    $reply_handler->setMailReceiver($revision);

    return $reply_handler;
  }

}
