<?php

final class PhabricatorMailSMSMessage
  extends PhabricatorMailExternalMessage {

  const MESSAGETYPE = 'sms';

  private $toNumber;
  private $textBody;

  public function newMailMessageEngine() {
    return new PhabricatorMailSMSEngine();
  }

  public function setToNumber(PhabricatorPhoneNumber $to_number) {
    $this->toNumber = $to_number;
    return $this;
  }

  public function getToNumber() {
    return $this->toNumber;
  }

  public function setTextBody($text_body) {
    $this->textBody = $text_body;
    return $this;
  }

  public function getTextBody() {
    return $this->textBody;
  }

}
