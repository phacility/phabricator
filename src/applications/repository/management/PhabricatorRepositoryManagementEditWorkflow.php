<?php

final class PhabricatorRepositoryManagementEditWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('edit')
      ->setExamples('**edit** --as __username__ __repository__ ...')
      ->setSynopsis(pht('Edit __repository__, named by callsign.'))
      ->setArguments(
        array(
          array(
            'name'        => 'repos',
            'wildcard'    => true,
          ),
          array(
            'name' => 'as',
            'param' => 'user',
            'help' => pht('Edit as user.'),
          ),
          array(
            'name' => 'local-path',
            'param' => 'path',
            'help' => pht('Edit the local path.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $repos = $this->loadRepositories($args, 'repos');

    if (!$repos) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more repositories to edit, by callsign.'));
    }

    $console = PhutilConsole::getConsole();

    // TODO: It would be nice to just take this action as "Administrator" or
    // similar, since that would make it easier to use this script, harder to
    // impersonate users, and more clear to viewers what happened. However,
    // the omnipotent user doesn't have a PHID right now, can't be loaded,
    // doesn't have a handle, etc. Adding all of that is fairly involved, and
    // I want to wait for stronger use cases first.

    $username = $args->getArg('as');
    if (!$username) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a user to edit as with %s.',
          '--as <username>'));
    }

    $actor = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->withUsernames(array($username))
      ->executeOne();

    if (!$actor) {
      throw new PhutilArgumentUsageException(
        pht("No such user '%s'!", $username));
    }

    foreach ($repos as $repo) {
      $console->writeOut("%s\n", pht("Editing '%s'...", $repo->getCallsign()));

      $xactions = array();

      $type_local_path = PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH;

      if ($args->getArg('local-path')) {
        $xactions[] = id(new PhabricatorRepositoryTransaction())
          ->setTransactionType($type_local_path)
          ->setNewValue($args->getArg('local-path'));
      }

      if (!$xactions) {
        throw new PhutilArgumentUsageException(
          pht('Specify one or more fields to edit!'));
      }

      $content_source = PhabricatorContentSource::newConsoleSource();

      $editor = id(new PhabricatorRepositoryEditor())
        ->setActor($actor)
        ->setContentSource($content_source)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($repo, $xactions);
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
