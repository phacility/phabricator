<?php

final class PhabricatorAuthManagementUntrustOAuthClientWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('untrust-oauth-client')
      ->setExamples('**untrust-oauth-client** [--id client_id]')
      ->setSynopsis(
        pht(
          'Set Phabricator to not trust an OAuth client. Phabricator '.
          'redirects to trusted OAuth clients that users have authorized '.
          'without user intervention.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('The id of the OAuth client.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $id = $args->getArg('id');

    if (!$id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify an OAuth client ID with %s.',
          '--id'));
    }

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($id))
      ->executeOne();

    if (!$client) {
      throw new PhutilArgumentUsageException(
        pht(
          'Failed to find an OAuth client with ID %s.', $id));
    }

    if (!$client->getIsTrusted()) {
      throw new PhutilArgumentUsageException(
        pht(
          'Phabricator already does not trust OAuth client "%s".',
          $client->getName()));
    }

    $client->setIsTrusted(0);
    $client->save();

    $console = PhutilConsole::getConsole();
    $console->writeOut(
      "%s\n",
      pht(
        'Updated; Phabricator does not trust OAuth client %s.',
        $client->getName()));
  }

}
