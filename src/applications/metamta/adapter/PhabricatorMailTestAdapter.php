<?php

/**
 * Mail adapter that doesn't actually send any email, for writing unit tests
 * against.
 */
final class PhabricatorMailTestAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'test';

  private $guts = array();

  private $supportsMessageID;
  private $failPermanently;
  private $failTemporarily;

  public function setSupportsMessageID($support) {
    $this->supportsMessageID = $support;
    return $this;
  }

  public function setFailPermanently($fail) {
    $this->failPermanently = true;
    return $this;
  }

  public function setFailTemporarily($fail) {
    $this->failTemporarily = true;
    return $this;
  }

  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailEmailMessage::MESSAGETYPE,
      PhabricatorMailSMSMessage::MESSAGETYPE,
    );
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap($options, array());
  }

  public function newDefaultOptions() {
    return array();
  }

  public function supportsMessageIDHeader() {
    return $this->supportsMessageID;
  }

  public function getGuts() {
    return $this->guts;
  }

  public function sendMessage(PhabricatorMailExternalMessage $message) {
    if ($this->failPermanently) {
      throw new PhabricatorMetaMTAPermanentFailureException(
        pht('Unit Test (Permanent)'));
    }

    if ($this->failTemporarily) {
      throw new Exception(
        pht('Unit Test (Temporary)'));
    }

    switch ($message->getMessageType()) {
      case PhabricatorMailEmailMessage::MESSAGETYPE:
        $guts = $this->newEmailGuts($message);
        break;
      case PhabricatorMailSMSMessage::MESSAGETYPE:
        $guts = $this->newSMSGuts($message);
        break;
    }

    $guts['did-send'] = true;
    $this->guts = $guts;
  }

  public function getBody() {
    return idx($this->guts, 'body');
  }

  public function getHTMLBody() {
    return idx($this->guts, 'html-body');
  }

  private function newEmailGuts(PhabricatorMailExternalMessage $message) {
    $guts = array();

    $from = $message->getFromAddress();
    $guts['from'] = (string)$from;

    $reply_to = $message->getReplyToAddress();
    if ($reply_to) {
      $guts['reply-to'] = (string)$reply_to;
    }

    $to_addresses = $message->getToAddresses();
    $to = array();
    foreach ($to_addresses as $address) {
      $to[] = (string)$address;
    }
    $guts['tos'] = $to;

    $cc_addresses = $message->getCCAddresses();
    $cc = array();
    foreach ($cc_addresses as $address) {
      $cc[] = (string)$address;
    }
    $guts['ccs'] = $cc;

    $subject = $message->getSubject();
    if (strlen($subject)) {
      $guts['subject'] = $subject;
    }

    $headers = $message->getHeaders();
    $header_list = array();
    foreach ($headers as $header) {
      $header_list[] = array(
        $header->getName(),
        $header->getValue(),
      );
    }
    $guts['headers'] = $header_list;

    $text_body = $message->getTextBody();
    if (phutil_nonempty_string($text_body)) {
      $guts['body'] = $text_body;
    }

    $html_body = $message->getHTMLBody();
    if (phutil_nonempty_string($html_body)) {
      $guts['html-body'] = $html_body;
    }

    $attachments = $message->getAttachments();
    $file_list = array();
    foreach ($attachments as $attachment) {
      $file_list[] = array(
        'data' => $attachment->getData(),
        'filename' => $attachment->getFilename(),
        'mimetype' => $attachment->getMimeType(),
      );
    }
    $guts['attachments'] = $file_list;

    return $guts;
  }

  private function newSMSGuts(PhabricatorMailExternalMessage $message) {
    $guts = array();

    $guts['to'] = $message->getToNumber();
    $guts['body'] = $message->getTextBody();

    return $guts;
  }

}
