<?php

abstract class PhabricatorPeopleManagementWorkflow
  extends PhabricatorManagementWorkflow {

  final protected function getUserSelectionArguments() {
    return array(
      array(
        'name' => 'user',
        'param' => 'username',
        'help' => pht('User account to act on.'),
      ),
    );
  }

  final protected function selectUser(PhutilArgumentParser $argv) {
    $username = $argv->getArg('user');

    if (!strlen($username)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Select a user account to act on with "--user <username>".'));
    }

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->withUsernames(array($username))
      ->executeOne();
    if (!$user) {
      throw new PhutilArgumentUsageException(
        pht(
          'No user with username "%s" exists.',
          $username));
    }

    return $user;
  }

  final protected function applyTransactions(
    PhabricatorUser $user,
    array $xactions) {
    assert_instances_of($xactions, 'PhabricatorUserTransaction');

    $viewer = $this->getViewer();
    $application = id(new PhabricatorPeopleApplication())->getPHID();
    $content_source = $this->newContentSource();

    $editor = $user->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setActingAsPHID($application)
      ->setContentSource($content_source)
      ->setContinueOnMissingFields(true);

    return $editor->applyTransactions($user, $xactions);
  }

}
