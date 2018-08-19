<?php

abstract class PhabricatorMailImplementationAdapter extends Phobject {

  private $key;
  private $priority;
  private $options = array();

  private $supportsInbound = true;
  private $supportsOutbound = true;

  final public function getAdapterType() {
    return $this->getPhobjectClassConstant('ADAPTERTYPE');
  }

  final public static function getAllAdapters() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getAdapterType')
      ->execute();
  }


  abstract public function setFrom($email, $name = '');
  abstract public function addReplyTo($email, $name = '');
  abstract public function addTos(array $emails);
  abstract public function addCCs(array $emails);
  abstract public function addAttachment($data, $filename, $mimetype);
  abstract public function addHeader($header_name, $header_value);
  abstract public function setBody($plaintext_body);
  abstract public function setHTMLBody($html_body);
  abstract public function setSubject($subject);


  /**
   * Some mailers, notably Amazon SES, do not support us setting a specific
   * Message-ID header.
   */
  abstract public function supportsMessageIDHeader();


  /**
   * Send the message. Generally, this means connecting to some service and
   * handing data to it.
   *
   * If the adapter determines that the mail will never be deliverable, it
   * should throw a @{class:PhabricatorMetaMTAPermanentFailureException}.
   *
   * For temporary failures, throw some other exception or return `false`.
   *
   * @return bool True on success.
   */
  abstract public function send();

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
  abstract public function newLegacyOptions();

  public function prepareForSend() {
    return;
  }

  protected function renderAddress($email, $name = null) {
    if (strlen($name)) {
      return (string)id(new PhutilEmailAddress())
        ->setDisplayName($name)
        ->setAddress($email);
    } else {
      return $email;
    }
  }

}
