<?php

/**
 * Defines the api for protocol adapters for @{class:PhabricatorBot}
 */
abstract class PhabricatorProtocolAdapter extends Phobject {

  private $config;

  public function setConfig($config) {
    $this->config = $config;
    return $this;
  }

  public function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  /**
   * Performs any connection logic necessary for the protocol
   */
  abstract public function connect();

  /**
   * Disconnect from the service.
   */
  public function disconnect() {
    return;
  }

  /**
   * This is the spout for messages coming in from the protocol.
   * This will be called in the main event loop of the bot daemon
   * So if if doesn't implement some sort of blocking timeout
   * (e.g. select-based socket polling), it should at least sleep
   * for some period of time in order to not overwhelm the processor.
   *
   * @param Int $poll_frequency The number of seconds between polls
   */
  abstract public function getNextMessages($poll_frequency);

  /**
   * This is the output mechanism for the protocol.
   *
   * @param PhabricatorBotMessage $message The message to write
   */
  abstract public function writeMessage(PhabricatorBotMessage $message);

  /**
   * String identifying the service type the adapter provides access to, like
   * "irc", "campfire", "flowdock", "hipchat", etc.
   */
  abstract public function getServiceType();

  /**
   * String identifying the service name the adapter is connecting to. This is
   * used to distinguish between instances of a service. For example, for IRC,
   * this should return the IRC network the client is connecting to.
   */
  abstract public function getServiceName();

}
