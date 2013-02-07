<?php

/**
 * Logs messages to stdout
 */
final class PhabricatorBotDebugLogHandler extends PhabricatorBotHandler {
  public function receiveMessage(PhabricatorBotMessage $message) {
    switch ($message->getCommand()) {
    case 'LOG':
      echo addcslashes(
        $message->getRawData(),
        "\0..\37\177..\377");
      echo "\n";
      break;
    }
  }
}
