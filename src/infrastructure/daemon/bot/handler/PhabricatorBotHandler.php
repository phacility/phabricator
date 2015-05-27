<?php

/**
 * Responds to IRC messages. You plug a bunch of these into a
 * @{class:PhabricatorBot} to give it special behavior.
 */
abstract class PhabricatorBotHandler {

  private $bot;

  final public function __construct(PhabricatorBot $irc_bot) {
    $this->bot = $irc_bot;
  }

  final protected function writeMessage(PhabricatorBotMessage $message) {
    $this->bot->writeMessage($message);
    return $this;
  }

  final protected function getConduit() {
    return $this->bot->getConduit();
  }

  final protected function getConfig($key, $default = null) {
    return $this->bot->getConfig($key, $default);
  }

  final protected function getURI($path) {
    $base_uri = new PhutilURI($this->bot->getConfig('conduit.uri'));
    $base_uri->setPath($path);
    return (string)$base_uri;
  }

  final protected function getServiceName() {
    return $this->bot->getAdapter()->getServiceName();
  }

  final protected function getServiceType() {
    return $this->bot->getAdapter()->getServiceType();
  }

  abstract public function receiveMessage(PhabricatorBotMessage $message);

  public function runBackgroundTasks() {
    return;
  }

  public function replyTo(PhabricatorBotMessage $original_message, $body) {
    if ($original_message->getCommand() != 'MESSAGE') {
      throw new Exception(
        pht('Handler is trying to reply to something which is not a message!'));
    }

    $reply = id(new PhabricatorBotMessage())
      ->setCommand('MESSAGE');

    if ($original_message->getTarget()->isPublic()) {
      // This is a public target, like a chatroom. Send the response to the
      // chatroom.
      $reply->setTarget($original_message->getTarget());
    } else {
      // This is a private target, like a private message. Send the response
      // back to the sender (presumably, we are the target).
      $reply->setTarget($original_message->getSender());
    }

    $reply->setBody($body);

    return $this->writeMessage($reply);
  }

}
