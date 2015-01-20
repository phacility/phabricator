<?php

final class PhabricatorMetaMTAReceivedMailTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testDropSelfMail() {
    $mail = new PhabricatorMetaMTAReceivedMail();
    $mail->setHeaders(
      array(
        'X-Phabricator-Sent-This-Message' => 'yes',
      ));
    $mail->save();

    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_FROM_PHABRICATOR,
      $mail->getStatus());
  }


  public function testDropDuplicateMail() {
    $mail_a = new PhabricatorMetaMTAReceivedMail();
    $mail_a->setHeaders(
      array(
        'Message-ID' => 'test@example.com',
      ));
    $mail_a->save();

    $mail_b = new PhabricatorMetaMTAReceivedMail();
    $mail_b->setHeaders(
      array(
        'Message-ID' => 'test@example.com',
      ));
    $mail_b->save();

    $mail_a->processReceivedMail();
    $mail_b->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_DUPLICATE,
      $mail_b->getStatus());
  }

  public function testDropUnreceivableMail() {
    $mail = new PhabricatorMetaMTAReceivedMail();
    $mail->setHeaders(
      array(
        'Message-ID' => 'test@example.com',
        'To'         => 'does+not+exist@example.com',
      ));
    $mail->save();

    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_NO_RECEIVERS,
      $mail->getStatus());
  }

  public function testDropUnknownSenderMail() {
    $this->setManiphestCreateEmail();

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phabricator.allow-email-users', false);
    $env->overrideEnvConfig('metamta.maniphest.default-public-author', null);

    $mail = new PhabricatorMetaMTAReceivedMail();
    $mail->setHeaders(
      array(
        'Message-ID' => 'test@example.com',
        'To'         => 'bugs@example.com',
        'From'       => 'does+not+exist@example.com',
      ));
    $mail->save();

    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_UNKNOWN_SENDER,
      $mail->getStatus());
  }


  public function testDropDisabledSenderMail() {
    $this->setManiphestCreateEmail();

    $user = $this->generateNewTestUser()
      ->setIsDisabled(true)
      ->save();

    $mail = new PhabricatorMetaMTAReceivedMail();
    $mail->setHeaders(
      array(
        'Message-ID'  => 'test@example.com',
        'From'        => $user->loadPrimaryEmail()->getAddress(),
        'To'          => 'bugs@example.com',
      ));
    $mail->save();

    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_DISABLED_SENDER,
      $mail->getStatus());
  }

  private function setManiphestCreateEmail() {
    $maniphest_app = new PhabricatorManiphestApplication();
    try {
      id(new PhabricatorMetaMTAApplicationEmail())
        ->setApplicationPHID($maniphest_app->getPHID())
        ->setAddress('bugs@example.com')
        ->setConfigData(array())
        ->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {}
  }

}
