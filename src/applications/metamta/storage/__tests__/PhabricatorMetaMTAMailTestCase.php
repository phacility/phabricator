<?php

final class PhabricatorMetaMTAMailTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testRecipients() {
    $user = $this->generateNewTestUser();
    $phid = $user->getPHID();

    $prefs = $user->loadPreferences();

    $mailer = new PhabricatorMailImplementationTestAdapter();

    $mail = new PhabricatorMetaMTAMail();
    $mail->addTos(array($phid));

    $this->assertEqual(
      true,
      in_array($phid, $mail->buildRecipientList()),
      '"To" is a recipient.');


    // Test that the "No Self Mail" preference works correctly.
    $mail->setFrom($phid);

    $this->assertEqual(
      true,
      in_array($phid, $mail->buildRecipientList()),
      '"From" does not exclude recipients by default.');

    $prefs->setPreference(
      PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL,
      true);
    $prefs->save();

    $this->assertEqual(
      false,
      in_array($phid, $mail->buildRecipientList()),
      '"From" excludes recipients with no-self-mail set.');

    $prefs->unsetPreference(
      PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL);
    $prefs->save();

    $this->assertEqual(
      true,
      in_array($phid, $mail->buildRecipientList()),
      '"From" does not exclude recipients by default.');


    // Test that explicit exclusion works correctly.
    $mail->setExcludeMailRecipientPHIDs(array($phid));

    $this->assertEqual(
      false,
      in_array($phid, $mail->buildRecipientList()),
      'Explicit exclude excludes recipients.');

    $mail->setExcludeMailRecipientPHIDs(array());


    // Test that mail tag preferences exclude recipients.
    $prefs->setPreference(
      PhabricatorUserPreferences::PREFERENCE_MAILTAGS,
      array(
        'test-tag' => false,
      ));
    $prefs->save();

    $mail->setMailTags(array('test-tag'));

    $this->assertEqual(
      false,
      in_array($phid, $mail->buildRecipientList()),
      'Tag preference excludes recipients.');

    $prefs->unsetPreference(PhabricatorUserPreferences::PREFERENCE_MAILTAGS);
    $prefs->save();

    $this->assertEqual(
      true,
      in_array($phid, $mail->buildRecipientList()),
      'Recipients restored after tag preference removed.');
  }

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
