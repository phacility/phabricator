<?php

final class PhabricatorMailAdapterTestCase
  extends PhabricatorTestCase {

  public function testSupportsMessageID() {
    $cases = array(
      array(
        pht('Amazon SES'),
        false,
        new PhabricatorMailAmazonSESAdapter(),
        array(
          'access-key' => 'test',
          'secret-key' => 'test',
          'region' => 'test',
          'endpoint' => 'test',
        ),
      ),

      array(
        pht('Mailgun'),
        true,
        new PhabricatorMailMailgunAdapter(),
        array(
          'api-key' => 'test',
          'domain' => 'test',
          'api-hostname' => 'test',
        ),
      ),

      array(
        pht('Sendmail'),
        true,
        new PhabricatorMailSendmailAdapter(),
        array(),
      ),

      array(
        pht('Sendmail (Explicit Config)'),
        false,
        new PhabricatorMailSendmailAdapter(),
        array(
          'message-id' => false,
        ),
      ),

      array(
        pht('SMTP (Local)'),
        true,
        new PhabricatorMailSMTPAdapter(),
        array(),
      ),

      array(
        pht('SMTP (Local + Explicit)'),
        false,
        new PhabricatorMailSMTPAdapter(),
        array(
          'message-id' => false,
        ),
      ),

      array(
        pht('SMTP (AWS)'),
        false,
        new PhabricatorMailSMTPAdapter(),
        array(
          'host' => 'test.amazonaws.com',
        ),
      ),

      array(
        pht('SMTP (AWS + Explicit)'),
        true,
        new PhabricatorMailSMTPAdapter(),
        array(
          'host' => 'test.amazonaws.com',
          'message-id' => true,
        ),
      ),

    );

    foreach ($cases as $case) {
      list($label, $expect, $mailer, $options) = $case;

      $defaults = $mailer->newDefaultOptions();
      $mailer->setOptions($options + $defaults);

      $actual = $mailer->supportsMessageIDHeader();

      $this->assertEqual($expect, $actual, pht('Message-ID: %s', $label));
    }
  }


}
