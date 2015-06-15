<?php

abstract class PhabricatorMailImplementationAdapter extends Phobject {

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

}
