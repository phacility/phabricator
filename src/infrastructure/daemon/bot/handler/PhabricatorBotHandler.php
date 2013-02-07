<?php

/**
 * Responds to IRC messages. You plug a bunch of these into a
 * @{class:PhabricatorBot} to give it special behavior.
 *
 * @group irc
 */
abstract class PhabricatorBotHandler {

  private $bot;

  final public function __construct(PhabricatorBot $irc_bot) {
    $this->bot = $irc_bot;
  }

  final protected function write($command, $message) {
    $this->bot->writeCommand($command, $message);
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

  final protected function isChannelName($name) {
    return (strncmp($name, '#', 1) === 0);
  }

  abstract public function receiveMessage(PhabricatorBotMessage $message);

  public function runBackgroundTasks() {
    return;
  }

}
