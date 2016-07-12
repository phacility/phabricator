<?php

final class AlmanacManagementUntrustKeyWorkflow
  extends AlmanacManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('untrust-key')
      ->setSynopsis(pht('Revoke trust of a public key.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('ID of the key to revoke trust for.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $id = $args->getArg('id');
    if (!$id) {
      throw new PhutilArgumentUsageException(
        pht('Specify a public key to revoke trust for with --id.'));
    }

    $key = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($id))
      ->executeOne();
    if (!$key) {
      throw new PhutilArgumentUsageException(
        pht('No public key exists with ID "%s".', $id));
    }

    if (!$key->getIsTrusted()) {
      throw new PhutilArgumentUsageException(
        pht('Public key with ID %s is not trusted.', $id));
    }

    $key->setIsTrusted(0);
    $key->save();

    $console->writeOut(
      "**<bg:green> %s </bg>** %s\n",
      pht('TRUST REVOKED'),
      pht('Trust has been revoked for public key %s.', $id));
  }

}
