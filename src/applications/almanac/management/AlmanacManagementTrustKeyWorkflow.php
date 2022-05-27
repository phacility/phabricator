<?php

final class AlmanacManagementTrustKeyWorkflow
  extends AlmanacManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('trust-key')
      ->setSynopsis(pht('Mark a public key as trusted.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('ID of the key to trust.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $id = $args->getArg('id');
    if (!$id) {
      throw new PhutilArgumentUsageException(
        pht('Specify a public key to trust with --id.'));
    }

    $key = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($id))
      ->executeOne();
    if (!$key) {
      throw new PhutilArgumentUsageException(
        pht('No public key exists with ID "%s".', $id));
    }

    if (!$key->getIsActive()) {
      throw new PhutilArgumentUsageException(
        pht('Public key "%s" is not an active key.', $id));
    }

    if ($key->getIsTrusted()) {
      throw new PhutilArgumentUsageException(
        pht('Public key with ID %s is already trusted.', $id));
    }

    if (!($key->getObject() instanceof AlmanacDevice)) {
      throw new PhutilArgumentUsageException(
        pht('You can only trust keys associated with Almanac devices.'));
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($key->getObject()->getPHID()))
      ->executeOne();

    $console->writeOut(
      "**<bg:red> %s </bg>**\n\n%s\n\n%s\n\n%s",
      pht('IMPORTANT!'),
      phutil_console_wrap(
        pht(
          'Trusting a public key gives anyone holding the corresponding '.
          'private key complete, unrestricted access to all data. The '.
          'private key will be able to sign requests that bypass policy and '.
          'security checks.')),
      phutil_console_wrap(
        pht(
          'This is an advanced feature which should normally be used only '.
          'when building a cluster. This feature is very dangerous if '.
          'misused.')),
      pht('This key is associated with device "%s".', $handle->getName()));

    $prompt = pht(
      'Really trust this key?');
    if (!phutil_console_confirm($prompt)) {
      throw new PhutilArgumentUsageException(
        pht('User aborted workflow.'));
    }

    $key->setIsTrusted(1);
    $key->save();

    PhabricatorAuthSSHKeyQuery::deleteSSHKeyCache();

    $console->writeOut(
      "**<bg:green> %s </bg>** %s\n",
      pht('TRUSTED'),
      pht('Key %s has been marked as trusted.', $id));
  }

}
