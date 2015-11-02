<?php

final class DrydockManagementCommandWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('command')
      ->setSynopsis(pht('Run a command on a leased resource.'))
      ->setArguments(
        array(
          array(
            'name' => 'lease',
            'param' => 'id',
            'help' => pht('Lease ID.'),
          ),
          array(
            'name' => 'argv',
            'wildcard' => true,
            'help' => pht('Command to execute.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $lease_id = $args->getArg('lease');
    if (!$lease_id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use %s to specify a lease.',
          '--lease'));
    }

    $argv = $args->getArg('argv');
    if (!$argv) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a command to run.'));
    }

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($lease_id))
      ->executeOne();
    if (!$lease) {
      throw new Exception(
        pht(
          'Unable to load lease with ID "%s"!',
          $lease_id));
    }

    // TODO: Check lease state, etc.

    $interface = $lease->getInterface(DrydockCommandInterface::INTERFACE_TYPE);

    list($stdout, $stderr) = call_user_func_array(
      array($interface, 'execx'),
      array('%Ls', $argv));

    fprintf(STDOUT, $stdout);
    fprintf(STDERR, $stderr);

    return 0;
  }

}
