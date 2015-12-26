<?php

final class PhabricatorObjectMailReceiverTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testDropUnconfiguredPublicMail() {
    list($task, $user, $mail) = $this->buildMail('public');

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('metamta.public-replies', false);

    $mail->save();
    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_NO_PUBLIC_MAIL,
      $mail->getStatus());
  }

/*

  TODO: Tasks don't support policies yet. Implement this once they do.

  public function testDropPolicyViolationMail() {
    list($task, $user, $mail) = $this->buildMail('public');

    // TODO: Set task policy to "no one" here.

    $mail->save();
    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_POLICY_PROBLEM,
      $mail->getStatus());
  }

*/

  public function testDropInvalidObjectMail() {
    list($task, $user, $mail) = $this->buildMail('404');

    $mail->save();
    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_NO_SUCH_OBJECT,
      $mail->getStatus());
  }

  public function testDropUserMismatchMail() {
    list($task, $user, $mail) = $this->buildMail('baduser');

    $mail->save();
    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_USER_MISMATCH,
      $mail->getStatus());
  }

  public function testDropHashMismatchMail() {
    list($task, $user, $mail) = $this->buildMail('badhash');

    $mail->save();
    $mail->processReceivedMail();

    $this->assertEqual(
      MetaMTAReceivedMailStatus::STATUS_HASH_MISMATCH,
      $mail->getStatus());
  }

  private function buildMail($style) {
    $user = $this->generateNewTestUser();

    $task = id(new PhabricatorManiphestTaskTestDataGenerator())
      ->setViewer($user)
      ->generateObject();

    $is_public = ($style === 'public');
    $is_bad_hash = ($style == 'badhash');
    $is_bad_user = ($style == 'baduser');
    $is_404_object = ($style == '404');

    if ($is_public) {
      $user_identifier = 'public';
    } else if ($is_bad_user) {
      $user_identifier = $user->getID() + 1;
    } else {
      $user_identifier = $user->getID();
    }

    if ($is_bad_hash) {
      $hash = PhabricatorObjectMailReceiver::computeMailHash('x', 'y');
    } else {
      $hash = PhabricatorObjectMailReceiver::computeMailHash(
        $task->getMailKey(),
        $is_public ? $task->getPHID() : $user->getPHID());
    }

    if ($is_404_object) {
      $task_identifier = 'T'.($task->getID() + 1);
    } else {
      $task_identifier = 'T'.$task->getID();
    }

    $to = $task_identifier.'+'.$user_identifier.'+'.$hash.'@example.com';

    $mail = new PhabricatorMetaMTAReceivedMail();
    $mail->setHeaders(
      array(
        'Message-ID' => 'test@example.com',
        'From'       => $user->loadPrimaryEmail()->getAddress(),
        'To'         => $to,
      ));

    return array($task, $user, $mail);
  }


}
