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

    $mailer = new PhabricatorMailTestAdapter();
    $mail->sendWithMailers(array($mailer));
    $this->assertEqual(
      PhabricatorMailOutboundStatus::STATUS_SENT,
      $mail->getStatus());


    // When the mailer fails temporarily, the mail should remain queued.
    $mail = new PhabricatorMetaMTAMail();
    $mail->addTos(array($phid));

    $mailer = new PhabricatorMailTestAdapter();
    $mailer->setFailTemporarily(true);
    try {
      $mail->sendWithMailers(array($mailer));
    } catch (Exception $ex) {
      // Ignore.
    }
    $this->assertEqual(
      PhabricatorMailOutboundStatus::STATUS_QUEUE,
      $mail->getStatus());


    // When the mailer fails permanently, the mail should be failed.
    $mail = new PhabricatorMetaMTAMail();
    $mail->addTos(array($phid));

    $mailer = new PhabricatorMailTestAdapter();
    $mailer->setFailPermanently(true);
    try {
      $mail->sendWithMailers(array($mailer));
    } catch (Exception $ex) {
      // Ignore.
    }
    $this->assertEqual(
      PhabricatorMailOutboundStatus::STATUS_FAIL,
      $mail->getStatus());
  }

  public function testRecipients() {
    $user = $this->generateNewTestUser();
    $phid = $user->getPHID();

    $mailer = new PhabricatorMailTestAdapter();

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

    $user = $this->writeSetting(
      $user,
      PhabricatorEmailSelfActionsSetting::SETTINGKEY,
      true);

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" excludes recipients with no-self-mail set.'));

    $user = $this->writeSetting(
      $user,
      PhabricatorEmailSelfActionsSetting::SETTINGKEY,
      null);

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" does not exclude recipients by default.'));

    $user = $this->writeSetting(
      $user,
      PhabricatorEmailNotificationsSetting::SETTINGKEY,
      true);

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" excludes recipients with no-mail set.'));

    $mail->setForceDelivery(true);

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('"From" includes no-mail recipients when forced.'));

    $mail->setForceDelivery(false);

    $user = $this->writeSetting(
      $user,
      PhabricatorEmailNotificationsSetting::SETTINGKEY,
      null);

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
    $user = $this->writeSetting(
      $user,
      PhabricatorEmailTagsSetting::SETTINGKEY,
      array(
        'test-tag' => false,
      ));

    $mail->setMailTags(array('test-tag'));

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('Tag preference excludes recipients.'));

    $user = $this->writeSetting(
      $user,
      PhabricatorEmailTagsSetting::SETTINGKEY,
      null);

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      'Recipients restored after tag preference removed.');

    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'userPHID = %s AND isPrimary = 1',
      $phid);

    $email->setIsVerified(0)->save();

    $this->assertFalse(
      in_array($phid, $mail->buildRecipientList()),
      pht('Mail not sent to unverified address.'));

    $email->setIsVerified(1)->save();

    $this->assertTrue(
      in_array($phid, $mail->buildRecipientList()),
      pht('Mail sent to verified address.'));
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

    $user = $this->generateNewTestUser();
    $phid = $user->getPHID();

    $mailer = new PhabricatorMailTestAdapter();

    $mailer->setSupportsMessageID($supports_message_id);

    $thread_id = 'somethread-12345';

    $mail = id(new PhabricatorMetaMTAMail())
      ->setThreadID($thread_id, $is_first_mail)
      ->addTos(array($phid))
      ->sendWithMailers(array($mailer));

    $guts = $mailer->getGuts();

    $headers = idx($guts, 'headers', array());

    $dict = array();
    foreach ($headers as $header) {
      list($name, $value) = $header;
      $dict[$name] = $value;
    }

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

  private function writeSetting(PhabricatorUser $user, $key, $value) {
    $preferences = PhabricatorUserPreferences::loadUserPreferences($user);

    $editor = id(new PhabricatorUserPreferencesEditor())
      ->setActor($user)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $xactions = array();
    $xactions[] = $preferences->newTransaction($key, $value);
    $editor->applyTransactions($preferences, $xactions);

    return id(new PhabricatorPeopleQuery())
      ->setViewer($user)
      ->withIDs(array($user->getID()))
      ->executeOne();
  }

  public function testMailerFailover() {
    $user = $this->generateNewTestUser();
    $phid = $user->getPHID();

    $status_sent = PhabricatorMailOutboundStatus::STATUS_SENT;
    $status_queue = PhabricatorMailOutboundStatus::STATUS_QUEUE;
    $status_fail = PhabricatorMailOutboundStatus::STATUS_FAIL;

    $mailer1 = id(new PhabricatorMailTestAdapter())
      ->setKey('mailer1');

    $mailer2 = id(new PhabricatorMailTestAdapter())
      ->setKey('mailer2');

    $mailers = array(
      $mailer1,
      $mailer2,
    );

    // Send mail with both mailers active. The first mailer should be used.
    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($phid))
      ->sendWithMailers($mailers);
    $this->assertEqual($status_sent, $mail->getStatus());
    $this->assertEqual('mailer1', $mail->getMailerKey());


    // If the first mailer fails, the mail should be sent with the second
    // mailer. Since we transmitted the mail, this doesn't raise an exception.
    $mailer1->setFailTemporarily(true);

    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($phid))
      ->sendWithMailers($mailers);
    $this->assertEqual($status_sent, $mail->getStatus());
    $this->assertEqual('mailer2', $mail->getMailerKey());


    // If both mailers fail, the mail should remain in queue.
    $mailer2->setFailTemporarily(true);

    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($phid));

    $caught = null;
    try {
      $mail->sendWithMailers($mailers);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof Exception);
    $this->assertEqual($status_queue, $mail->getStatus());
    $this->assertEqual(null, $mail->getMailerKey());

    $mailer1->setFailTemporarily(false);
    $mailer2->setFailTemporarily(false);


    // If the first mailer fails permanently, the mail should fail even though
    // the second mailer isn't configured to fail.
    $mailer1->setFailPermanently(true);

    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($phid));

    $caught = null;
    try {
      $mail->sendWithMailers($mailers);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof Exception);
    $this->assertEqual($status_fail, $mail->getStatus());
    $this->assertEqual(null, $mail->getMailerKey());
  }

  public function testMailSizeLimits() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('metamta.email-body-limit', 1024 * 512);

    $user = $this->generateNewTestUser();
    $phid = $user->getPHID();

    $string_1kb = str_repeat('x', 1024);
    $html_1kb = str_repeat('y', 1024);
    $string_1mb = str_repeat('x', 1024 * 1024);
    $html_1mb = str_repeat('y', 1024 * 1024);

    // First, send a mail with a small text body and a small HTML body to make
    // sure the basics work properly.
    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($phid))
      ->setBody($string_1kb)
      ->setHTMLBody($html_1kb);

    $mailer = new PhabricatorMailTestAdapter();
    $mail->sendWithMailers(array($mailer));
    $this->assertEqual(
      PhabricatorMailOutboundStatus::STATUS_SENT,
      $mail->getStatus());

    $text_body = $mailer->getBody();
    $html_body = $mailer->getHTMLBody();

    $this->assertEqual($string_1kb, $text_body);
    $this->assertEqual($html_1kb, $html_body);


    // Now, send a mail with a large text body and a large HTML body. We expect
    // the text body to be truncated and the HTML body to be dropped.
    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($phid))
      ->setBody($string_1mb)
      ->setHTMLBody($html_1mb);

    $mailer = new PhabricatorMailTestAdapter();
    $mail->sendWithMailers(array($mailer));
    $this->assertEqual(
      PhabricatorMailOutboundStatus::STATUS_SENT,
      $mail->getStatus());

    $text_body = $mailer->getBody();
    $html_body = $mailer->getHTMLBody();

    // We expect the body was truncated, because it exceeded the body limit.
    $this->assertTrue(
      (strlen($text_body) < strlen($string_1mb)),
      pht('Text Body Truncated'));

    // We expect the HTML body was dropped completely after the text body was
    // truncated.
    $this->assertTrue(
      !phutil_nonempty_string($html_body),
      pht('HTML Body Removed'));


    // Next send a mail with a small text body and a large HTML body. We expect
    // the text body to be intact and the HTML body to be dropped.
    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($phid))
      ->setBody($string_1kb)
      ->setHTMLBody($html_1mb);

    $mailer = new PhabricatorMailTestAdapter();
    $mail->sendWithMailers(array($mailer));
    $this->assertEqual(
      PhabricatorMailOutboundStatus::STATUS_SENT,
      $mail->getStatus());

    $text_body = $mailer->getBody();
    $html_body = $mailer->getHTMLBody();

    $this->assertEqual($string_1kb, $text_body);
    $this->assertTrue(!phutil_nonempty_string($html_body));
  }

}
