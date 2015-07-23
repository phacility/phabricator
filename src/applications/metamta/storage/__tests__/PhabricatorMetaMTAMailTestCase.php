<?php

final class PhabricatorMetaMTAMailTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testMailSendFailures() {
    $user = $this->generateNewTestUser();
    $phid = $user->getPHID();


    // Normally, the send should succeed.
    $mail = new PhabricatorMetaMTAMail();
    $mail->addTos(array($phid));

    $mailer = new PhabricatorMailImplementationTestAdapter();
    $mail->sendNow($force = true, $mailer);
    $this->assertEqual(
      PhabricatorMetaMTAMail::STATUS_SENT,
      $mail->getStatus());


    // When the mailer fails temporarily, the mail should remain queued.
    $mail = new PhabricatorMetaMTAMail();
    $mail->addTos(array($phid));

    $mailer = new PhabricatorMailImplementationTestAdapter();
    $mailer->setFailTemporarily(true);
    try {
      $mail->sendNow($force = true, $mailer);
    } catch (Exception $ex) {
      // Ignore.
    }
    $this->assertEqual(
      PhabricatorMetaMTAMail::STATUS_QUEUE,
      $mail->getStatus());


    // When the mailer fails permanently, the mail should be failed.
    $mail = new PhabricatorMetaMTAMail();
    $mail->addTos(array($phid));

    $mailer = new PhabricatorMailImplementationTestAdapter();
    $mailer->setFailPermanently(true);
    try {
      $mail->sendNow($force = true, $mailer);
    } catch (Exception $ex) {
      // Ignore.
    }
    $this->assertEqual(
      PhabricatorMetaMTAMail::STATUS_FAIL,
      $mail->getStatus());
  }

  public function testRecipients() {
    $user = $this->generateNewTestUser();
    $phid = $user->getPHID();

    $prefs = $user->loadPreferences();

    $mailer = new PhabricatorMailImplementationTestAdapter();

    $mail = new PhabricatorMetaMTAMail();
    $mail->addTos(array($phid));

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('"To" is a recipient.'));


    // Test that the "No Self Mail" and "No Mail" preferences work correctly.
    $mail->setFrom($phid);

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" does not exclude recipients by default.'));

    $prefs->setPreference(
      PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL,
      true);
    $prefs->save();

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" excludes recipients with no-self-mail set.'));

    $prefs->unsetPreference(
      PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL);
    $prefs->save();

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" does not exclude recipients by default.'));

    $prefs->setPreference(
      PhabricatorUserPreferences::PREFERENCE_NO_MAIL,
      true);
    $prefs->save();

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" excludes recipients with no-mail set.'));

    $mail->setForceDelivery(true);

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" includes no-mail recipients when forced.'));

    $mail->setForceDelivery(false);

    $prefs->unsetPreference(
      PhabricatorUserPreferences::PREFERENCE_NO_MAIL);
    $prefs->save();

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" does not exclude recipients by default.'));


    // Test that explicit exclusion works correctly.
    $mail->setExcludeMailRecipientPHIDs(array($phid));

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('Explicit exclude excludes recipients.'));

    $mail->setExcludeMailRecipientPHIDs(array());


    // Test that mail tag preferences exclude recipients.
    $prefs->setPreference(
      PhabricatorUserPreferences::PREFERENCE_MAILTAGS,
      array(
        'test-tag' => false,
      ));
    $prefs->save();

    $mail->setMailTags(array('test-tag'));

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('Tag preference excludes recipients.'));

    $prefs->unsetPreference(PhabricatorUserPreferences::PREFERENCE_MAILTAGS);
    $prefs->save();

    $this->assertTrue(
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

    $case = '<message-id = '.($supports_message_id ? 'Y' : 'N').', '.
            'first = '.($is_first_mail ? 'Y' : 'N').'>';

    $this->assertTrue(
      isset($dict['Thread-Index']),
      pht('Expect Thread-Index header for case %s.', $case));
    $this->assertEqual(
      $expect_message_id,
      isset($dict['Message-ID']),
      pht(
        'Expectation about existence of Message-ID header for case %s.',
        $case));
    $this->assertEqual(
      $expect_in_reply_to,
      isset($dict['In-Reply-To']),
      pht(
        'Expectation about existence of In-Reply-To header for case %s.',
        $case));
    $this->assertEqual(
      $expect_references,
      isset($dict['References']),
      pht(
        'Expectation about existence of References header for case %s.',
        $case));
  }

}
