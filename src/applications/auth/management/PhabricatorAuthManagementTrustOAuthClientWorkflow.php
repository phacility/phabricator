<?php

final class PhabricatorAuthManagementTrustOAuthClientWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('trust-oauth-client')
      ->setExamples('**trust-oauth-client** [--id client_id]')
      ->setSynopsis(
        pht(
          'Set Phabricator to trust an OAuth client. Phabricator '.
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
          'Specify an OAuth client id with %s.',
          '--id'));
    }

    $client = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($id))
      ->executeOne();

    if (!$client) {
      throw new PhutilArgumentUsageException(
        pht(
          'Failed to find an OAuth client with id %s.', $id));
    }

    if ($client->getIsTrusted()) {
      throw new PhutilArgumentUsageException(
        pht(
          'Phabricator already trusts OAuth client "%s".',
          $client->getName()));
    }

    $client->setIsTrusted(1);
    $client->save();

    $console = PhutilConsole::getConsole();
    $console->writeOut(
      "%s\n",
      pht(
        'Updated; Phabricator trusts OAuth client %s.',
        $client->getName()));
  }

}
