<?php

final class PhabricatorAuthManagementVerifyWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('verify')
      ->setExamples('**verify** __email__')
      ->setSynopsis(
        pht(
          'Verify an unverified email address which is already attached to '.
          'an account. This will also re-execute event hooks for addresses '.
          'which are already verified.'))
      ->setArguments(
        array(
          array(
            'name' => 'email',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $emails = $args->getArg('email');
    if (!$emails) {
      throw new PhutilArgumentUsageException(
        pht('You must specify the email to verify.'));
    } else if (count($emails) > 1) {
      throw new PhutilArgumentUsageException(
        pht('You can only verify one address at a time.'));
    }
    $address = head($emails);

    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $address);
    if (!$email) {
      throw new PhutilArgumentUsageException(
        pht(
          'No email exists with address "%s"!',
          $address));
    }

    $viewer = $this->getViewer();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($email->getUserPHID()))
      ->executeOne();
    if (!$user) {
      throw new Exception(pht('Email record has invalid user PHID!'));
    }

    $editor = id(new PhabricatorUserEditor())
      ->setActor($viewer)
      ->verifyEmail($user, $email);

    $console = PhutilConsole::getConsole();

    $console->writeOut(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
