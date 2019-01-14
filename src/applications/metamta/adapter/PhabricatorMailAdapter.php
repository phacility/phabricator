<?php

abstract class PhabricatorMailAdapter
  extends Phobject {

  private $key;
  private $priority;
  private $media;
  private $options = array();

  private $supportsInbound = true;
  private $supportsOutbound = true;
  private $mediaMap;

  final public function getAdapterType() {
    return $this->getPhobjectClassConstant('ADAPTERTYPE');
  }

  final public static function getAllAdapters() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getAdapterType')
      ->execute();
  }

  abstract public function getSupportedMessageTypes();
  abstract public function sendMessage(PhabricatorMailExternalMessage $message);

  /**
   * Return true if this adapter supports setting a "Message-ID" when sending
   * email.
   *
   * This is an ugly implementation detail because mail threading is a horrible
   * mess, implemented differently by every client in existence.
   */
  public function supportsMessageIDHeader() {
    return false;
  }

  final public function supportsMessageType($message_type) {
    if ($this->mediaMap === null) {
      $media_map = $this->getSupportedMessageTypes();
      $media_map = array_fuse($media_map);

      if ($this->media) {
        $config_map = $this->media;
        $config_map = array_fuse($config_map);

        $media_map = array_intersect_key($media_map, $config_map);
      }

      $this->mediaMap = $media_map;
    }

    return isset($this->mediaMap[$message_type]);
  }

  final public function setMedia(array $media) {
    $native_map = $this->getSupportedMessageTypes();
    $native_map = array_fuse($native_map);

    foreach ($media as $medium) {
      if (!isset($native_map[$medium])) {
        throw new Exception(
          pht(
            'Adapter ("%s") is configured for medium "%s", but this is not '.
            'a supported delivery medium. Supported media are: %s.',
            $medium,
            implode(', ', $native_map)));
      }
    }

    $this->media = $media;
    $this->mediaMap = null;
    return $this;
  }

  final public function getMedia() {
    return $this->media;
  }

  final public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  final public function getKey() {
    return $this->key;
  }

  final public function setPriority($priority) {
    $this->priority = $priority;
    return $this;
  }

  final public function getPriority() {
    return $this->priority;
  }

  final public function setSupportsInbound($supports_inbound) {
    $this->supportsInbound = $supports_inbound;
    return $this;
  }

  final public function getSupportsInbound() {
    return $this->supportsInbound;
  }

  final public function setSupportsOutbound($supports_outbound) {
    $this->supportsOutbound = $supports_outbound;
    return $this;
  }

  final public function getSupportsOutbound() {
    return $this->supportsOutbound;
  }

  final public function getOption($key) {
    if (!array_key_exists($key, $this->options)) {
      throw new Exception(
        pht(
          'Mailer ("%s") is attempting to access unknown option ("%s").',
          get_class($this),
          $key));
    }

    return $this->options[$key];
  }

  final public function setOptions(array $options) {
    $this->validateOptions($options);
    $this->options = $options;
    return $this;
  }

  abstract protected function validateOptions(array $options);

  abstract public function newDefaultOptions();

}
