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

final class PhabricatorMetaMTAEmailBodyParser {

  public function stripTextBody($body) {
    return $this->stripSignature($this->stripQuotedText($body));
  }

  private function stripQuotedText($body) {
    $body = preg_replace(
      '/^\s*On\b.*\bwrote:.*?/msU',
      '',
      $body);

    // Outlook english
    $body = preg_replace(
      '/^\s*-----Original Message-----.*?/imsU',
      '',
      $body);

    // Outlook danish
    $body = preg_replace(
      '/^\s*-----Oprindelig Meddelelse-----.*?/imsU',
      '',
      $body);

    return rtrim($body);
  }

  private function stripSignature($body) {
    // Quasi-"standard" delimiter, for lols see:
    //   https://bugzilla.mozilla.org/show_bug.cgi?id=58406
    $body = preg_replace(
      '/^-- +$.*/sm',
      '',
      $body);

    // HTC Mail application (mobile)
    $body = preg_replace(
      '/^\s*^Sent from my HTC smartphone.*/sm',
      '',
      $body);

    // Apple iPhone
    $body = preg_replace(
      '/^\s*^Sent from my iPhone\s*$.*/sm',
      '',
      $body);

    return rtrim($body);
  }

}
