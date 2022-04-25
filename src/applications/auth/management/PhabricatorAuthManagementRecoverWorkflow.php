<?php

final class PhabricatorAuthManagementRecoverWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('recover')
      ->setExamples('**recover** __username__')
      ->setSynopsis(
        pht(
          'Recover access to an account if you have locked yourself out.'))
      ->setArguments(
        array(
          array(
            'name' => 'force-full-session',
            'help' => pht(
              'Recover directly into a full session without requiring MFA '.
              'or other login checks.'),
          ),
          array(
            'name' => 'username',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $usernames = $args->getArg('username');
    if (!$usernames) {
      throw new PhutilArgumentUsageException(
        pht('You must specify the username of the account to recover.'));
    } else if (count($usernames) > 1) {
      throw new PhutilArgumentUsageException(
        pht('You can only recover the username for one account.'));
    }

    $username = head($usernames);

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->withUsernames(array($username))
      ->executeOne();

    if (!$user) {
      throw new PhutilArgumentUsageException(
        pht(
          'No such user "%s" to recover.',
          $username));
    }

    if (!$user->canEstablishWebSessions()) {
      throw new PhutilArgumentUsageException(
        pht(
          'This account ("%s") can not establish web sessions, so it is '.
          'not possible to generate a functional recovery link. Special '.
          'accounts like daemons and mailing lists can not log in via the '.
          'web UI.',
          $username));
    }

    $force_full_session = $args->getArg('force-full-session');

    $engine = new PhabricatorAuthSessionEngine();
    $onetime_uri = $engine->getOneTimeLoginURI(
      $user,
      null,
      PhabricatorAuthSessionEngine::ONETIME_RECOVER,
      $force_full_session);

    $console = PhutilConsole::getConsole();
    $console->writeOut(
      pht(
        'Use this link to recover access to the "%s" account from the web '.
        'interface:',
        $username));
    $console->writeOut("\n\n");
    $console->writeOut('    %s', $onetime_uri);
    $console->writeOut("\n\n");
    $console->writeOut(
      "%s\n",
      pht(
        'After logging in, you can use the "Auth" application to add or '.
        'restore authentication providers and allow normal logins to '.
        'succeed.'));

    return 0;
  }

}
