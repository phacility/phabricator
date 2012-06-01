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
