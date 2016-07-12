<?php

final class PhabricatorAuthManagementUnlimitWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('unlimit')
      ->setExamples('**unlimit** --user __username__ --all')
      ->setSynopsis(
        pht(
          'Reset action counters so a user can continue taking '.
          'rate-limited actions.'))
      ->setArguments(
        array(
          array(
            'name' => 'user',
            'param' => 'username',
            'help' => pht('Reset action counters for this user.'),
          ),
          array(
            'name' => 'all',
            'help' => pht('Reset all counters.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $username = $args->getArg('user');
    if (!strlen($username)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use %s to choose a user to reset actions for.', '--user'));
    }

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->withUsernames(array($username))
      ->executeOne();
    if (!$user) {
      throw new PhutilArgumentUsageException(
        pht(
          'No user exists with username "%s".',
          $username));
    }

    $all = $args->getArg('all');
    if (!$all) {
      // TODO: Eventually, let users reset specific actions. For now, we
      // require `--all` so that usage won't change when you can reset in a
      // more tailored way.
      throw new PhutilArgumentUsageException(
        pht(
          'Specify %s to reset all action counters.', '--all'));
    }

    $count = PhabricatorSystemActionEngine::resetActions(
      array(
        $user->getPHID(),
      ));

    echo pht('Reset %s action(s).', new PhutilNumber($count))."\n";

    return 0;
  }

}
