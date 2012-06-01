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

final class PhabricatorMetaMTAMailTestCase extends PhabricatorTestCase {

  public function testThreadIDHeaders() {
    $this->runThreadIDHeadersWithConfiguration(true, true);
    $this->runThreadIDHeadersWithConfiguration(true, false);
    $this->runThreadIDHeadersWithConfiguration(false, true);
    $this->runThreadIDHeadersWithConfiguration(false, false);
  }

  private function runThreadIDHeadersWithConfiguration(
    $supports_message_id,
    $is_first_mail) {

    $mailer = new PhabricatorMailImplementationTestAdapter(
      array(
        'supportsMessageIDHeader' => $supports_message_id,
      ));

    $thread_id = '<somethread-12345@somedomain.tld>';

    $mail = new PhabricatorMetaMTAMail();
    $mail->setThreadID($thread_id, $is_first_mail);
    $mail->sendNow($force = true, $mailer);

    $guts = $mailer->getGuts();
    $dict = ipull($guts['headers'], 1, 0);

    if ($is_first_mail && $supports_message_id) {
      $expect_message_id = true;
      $expect_in_reply_to = false;
      $expect_references = false;
    } else {
      $expect_message_id = false;
      $expect_in_reply_to = true;
      $expect_references = true;
    }

    $case = "<message-id = ".($supports_message_id ? 'Y' : 'N').", ".
            "first = ".($is_first_mail ? 'Y' : 'N').">";

    $this->assertEqual(
      true,
      isset($dict['Thread-Index']),
      "Expect Thread-Index header for case {$case}.");
    $this->assertEqual(
      $expect_message_id,
      isset($dict['Message-ID']),
      "Expectation about existence of Message-ID header for case {$case}.");
    $this->assertEqual(
      $expect_in_reply_to,
      isset($dict['In-Reply-To']),
      "Expectation about existence of In-Reply-To header for case {$case}.");
    $this->assertEqual(
      $expect_references,
      isset($dict['References']),
      "Expectation about existence of References header for case {$case}.");
  }

}
