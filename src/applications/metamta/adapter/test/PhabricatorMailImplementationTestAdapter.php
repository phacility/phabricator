<?php

/*
 * Copyright 2012 Facebook, Inc.
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

/**
 * Mail adapter that doesn't actually send any email, for writing unit tests
 * against.
 */
final class PhabricatorMailImplementationTestAdapter
  extends PhabricatorMailImplementationAdapter {

  private $guts = array();
  private $config;

  public function __construct(array $config) {
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
      'mimetype' => $mimetype
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

  public function setSubject($subject) {
    $this->guts['subject'] = $subject;
    return $this;
  }

  public function setIsHTML($is_html) {
    $this->guts['is-html'] = $is_html;
    return $this;
  }

  public function supportsMessageIDHeader() {
    return $this->config['supportsMessageIDHeader'];
  }

  public function send() {
    $this->guts['did-send'] = true;
    return true;
  }

  public function getGuts() {
    return $this->guts;
  }

}
