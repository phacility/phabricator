<?php

final class PhabricatorMailManagementUnverifyWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('unverify')
      ->setSynopsis(
        pht('Unverify an email address so it no longer receives mail.'))
      ->setExamples('**unverify** __address__ ...')
      ->setArguments(
        array(
          array(
            'name' => 'addresses',
            'wildcard' => true,
            'help' => pht('Address (or addresses) to unverify.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $addresses = $args->getArg('addresses');
    if (!$addresses) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more email addresses to unverify.'));
    }

    foreach ($addresses as $address) {
      $email = id(new PhabricatorUserEmail())->loadOneWhere(
        'address = %s',
        $address);
      if (!$email) {
        echo tsprintf(
          "%s\n",
          pht(
            'Address "%s" is unknown.',
            $address));
        continue;
      }

      $user_phid = $email->getUserPHID();

      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($user_phid))
        ->executeOne();

      if (!$user) {
        echo tsprintf(
          "%s\n",
          pht(
            'Address "%s" belongs to invalid user "%s".',
            $address,
            $user_phid));
        continue;
      }

      if (!$email->getIsVerified()) {
        echo tsprintf(
          "%s\n",
          pht(
            'Address "%s" (owned by "%s") is already unveriifed.',
            $address,
            $user->getUsername()));
        continue;
      }

      $email->openTransaction();

        $email
          ->setIsVerified(0)
          ->save();

        if ($email->getIsPrimary()) {
          $user
            ->setIsEmailVerified(0)
            ->save();
        }

      $email->saveTransaction();

      if ($email->getIsPrimary()) {
        echo tsprintf(
          "%s\n",
          pht(
            'Unverified "%s", the primary address for "%s".',
            $address,
            $user->getUsername()));
      } else {
        echo tsprintf(
          "%s\n",
          pht(
            'Unverified "%s", an address for "%s".',
            $address,
            $user->getUsername()));
      }
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
