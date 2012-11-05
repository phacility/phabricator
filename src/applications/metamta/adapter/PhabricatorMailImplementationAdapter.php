<?php

abstract class PhabricatorMailImplementationAdapter {

  abstract public function setFrom($email, $name = '');
  abstract public function addReplyTo($email, $name = '');
  abstract public function addTos(array $emails);
  abstract public function addCCs(array $emails);
  abstract public function addAttachment($data, $filename, $mimetype);
  abstract public function addHeader($header_name, $header_value);
  abstract public function setBody($body);
  abstract public function setSubject($subject);
  abstract public function setIsHTML($is_html);

  /**
   * Some mailers, notably Amazon SES, do not support us setting a specific
   * Message-ID header.
   */
  abstract public function supportsMessageIDHeader();

  abstract public function send();

}
