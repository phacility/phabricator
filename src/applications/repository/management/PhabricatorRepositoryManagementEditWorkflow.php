<?php

final class PhabricatorRepositoryManagementEditWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('edit')
      ->setExamples('**edit** --as __username__ __repository__ ...')
      ->setSynopsis(
        pht(
          'Edit __repository__ (will eventually be deprecated by Conduit).'))
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
          array(
            'name' => 'serve-http',
            'param' => 'string',
            'help' => pht('Edit the http serving policy.'),
          ),
          array(
            'name' => 'serve-ssh',
            'param' => 'string',
            'help' => pht('Edit the ssh serving policy.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $repos = $this->loadRepositories($args, 'repos');

    if (!$repos) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more repositories to edit.'));
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
      $console->writeOut(
        "%s\n",
        pht(
          'Editing "%s"...',
          $repo->getDisplayName()));

      $xactions = array();

      $type_local_path = PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH;
      $type_protocol_http =
        PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP;
      $type_protocol_ssh = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH;
      $allowed_serve_modes = array(
        PhabricatorRepository::SERVE_OFF,
        PhabricatorRepository::SERVE_READONLY,
        PhabricatorRepository::SERVE_READWRITE,
      );

      if ($args->getArg('local-path')) {
        $xactions[] = id(new PhabricatorRepositoryTransaction())
          ->setTransactionType($type_local_path)
          ->setNewValue($args->getArg('local-path'));
      }
      $serve_http = $args->getArg('serve-http');
      if ($serve_http && in_array($serve_http, $allowed_serve_modes)) {
        $xactions[] = id(new PhabricatorRepositoryTransaction())
          ->setTransactionType($type_protocol_http)
          ->setNewValue($serve_http);
      }
      $serve_ssh = $args->getArg('serve-ssh');
      if ($serve_ssh && in_array($serve_ssh, $allowed_serve_modes)) {
        $xactions[] = id(new PhabricatorRepositoryTransaction())
          ->setTransactionType($type_protocol_ssh)
          ->setNewValue($serve_ssh);
      }


      if (!$xactions) {
        throw new PhutilArgumentUsageException(
          pht('Specify one or more fields to edit!'));
      }

      $content_source = $this->newContentSource();

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
