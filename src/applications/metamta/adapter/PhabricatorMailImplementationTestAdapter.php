<?php

/**
 * Mail adapter that doesn't actually send any email, for writing unit tests
 * against.
 */
final class PhabricatorMailImplementationTestAdapter
  extends PhabricatorMailImplementationAdapter {

  private $guts = array();
  private $config;

  public function __construct(array $config = array()) {
    $this->config = $config;
  }

  public function setFrom($email, $name = '') {
    $this->guts['from'] = $email;
    $this->guts['from-name'] = $name;
    return $this;
  }

  public function addReplyTo($email, $name = '') {
    if (empty($this->guts['reply-to'])) {
      $this->guts['reply-to'] = array();
    }
    $this->guts['reply-to'][] = array(
      'email' => $email,
      'name'  => $name,
    );
    return $this;
  }

  public function addTos(array $emails) {
    foreach ($emails as $email) {
      $this->guts['tos'][] = $email;
    }
    return $this;
  }

  public function addCCs(array $emails) {
    foreach ($emails as $email) {
      $this->guts['ccs'][] = $email;
    }
    return $this;
  }

  public function addAttachment($data, $filename, $mimetype) {
    $this->guts['attachments'][] = array(
      'data' => $data,
      'filename' => $filename,
      'mimetype' => $mimetype,
    );
    return $this;
  }

  public function addHeader($header_name, $header_value) {
    $this->guts['headers'][] = array($header_name, $header_value);
    return $this;
  }

  public function setBody($body) {
    $this->guts['body'] = $body;
    return $this;
  }

  public function setHTMLBody($html_body) {
    $this->guts['html-body'] = $html_body;
    return $this;
  }

  public function setSubject($subject) {
    $this->guts['subject'] = $subject;
    return $this;
  }

  public function supportsMessageIDHeader() {
    return idx($this->config, 'supportsMessageIDHeader', true);
  }

  public function send() {
    if (!empty($this->guts['fail-permanently'])) {
      throw new PhabricatorMetaMTAPermanentFailureException(
        pht('Unit Test (Permanent)'));
    }

    if (!empty($this->guts['fail-temporarily'])) {
      throw new Exception(
        pht('Unit Test (Temporary)'));
    }

    $this->guts['did-send'] = true;
    return true;
  }

  public function getGuts() {
    return $this->guts;
  }

  public function setFailPermanently($fail) {
    $this->guts['fail-permanently'] = $fail;
    return $this;
  }

  public function setFailTemporarily($fail) {
    $this->guts['fail-temporarily'] = $fail;
    return $this;
  }

}
