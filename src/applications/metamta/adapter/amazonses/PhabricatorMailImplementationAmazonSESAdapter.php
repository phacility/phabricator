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

class PhabricatorMailImplementationAmazonSESAdapter
  extends PhabricatorMailImplementationAdapter {

  private $message;
  private $isHTML;

  public function __construct() {
    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/amazon-ses/ses.php';
    $this->message = newv('SimpleEmailServiceMessage', array());
  }

  public function setFrom($email) {
    $this->message->setFrom($email);
    return $this;
  }

  public function addReplyTo($email) {
    $this->message->addReplyTo($email);
    return $this;
  }

  public function addTos(array $emails) {
    foreach ($emails as $email) {
      $this->message->addTo($email);
    }
    return $this;
  }

  public function addCCs(array $emails) {
    foreach ($emails as $email) {
      $this->message->addCC($email);
    }
    return $this;
  }

  public function addHeader($header_name, $header_value) {
    // SES does not currently support custom headers.
    return $this;
  }

  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  public function setSubject($subject) {
    $this->message->setSubject($subject);
    return $this;
  }

  public function setIsHTML($is_html) {
    $this->isHTML = true;
    return $this;
  }

  public function hasValidRecipients() {
    return true;
  }

  public function send() {
    if ($this->isHTML) {
      $this->message->setMessageFromString($this->body, $this->body);
    } else {
      $this->message->setMessageFromString($this->body);
    }

    $key = PhabricatorEnv::getEnvConfig('amazon-ses.access-key');
    $secret = PhabricatorEnv::getEnvConfig('amazon-ses.secret-key');

    $service = new SimpleEmailService($key, $secret);
    return $service->sendEmail($this->message);
  }

}
