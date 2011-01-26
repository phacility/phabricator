<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorMailImplementationPHPMailerLiteAdapter
  extends PhabricatorMailImplementationAdapter {

  public function __construct() {
    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/phpmailer/class.phpmailer-lite.php';
    $this->mailer = newv('PHPMailerLite', array($use_exceptions = true));
  }

  public function setFrom($email) {
    $this->mailer->SetFrom($email);
    return $this;
  }

  public function addReplyTo($email) {
    $this->mailer->AddReplyTo($email);
    return $this;
  }

  public function addTos(array $emails) {
    foreach ($emails as $email) {
      $this->mailer->AddAddress($email);
    }
    return $this;
  }

  public function addCCs(array $emails) {
    foreach ($emails as $email) {
      $this->mailer->AddCC($email);
    }
    return $this;
  }

  public function addHeader($header_name, $header_value) {
    $this->mailer->AddCustomHeader($header_name.': '.$header_value);
    return $this;
  }

  public function setBody($body) {
    $this->mailer->Body = $body;
    return $this;
  }

  public function setSubject($subject) {
    $this->mailer->Subject = $subject;
    return $this;
  }

  public function setIsHTML($is_html) {
    $this->mailer->IsHTML(true);
    return $this;
  }

  public function hasValidRecipients() {
    return true;
  }

  public function send() {
    return $this->mailer->Send();
  }

}
