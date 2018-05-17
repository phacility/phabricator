<?php

final class PhabricatorConduitCallManagementWorkflow
  extends PhabricatorConduitManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('call')
      ->setSynopsis(pht('Call a Conduit method..'))
      ->setArguments(
        array(
          array(
            'name' => 'method',
            'param' => 'method',
            'help' => pht('Method to call.'),
          ),
          array(
            'name' => 'input',
            'param' => 'input',
            'help' => pht(
              'File to read parameters from, or "-" to read from '.
              'stdin.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $method = $args->getArg('method');
    if (!strlen($method)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a method to call with "--method".'));
    }

    $input = $args->getArg('input');
    if (!strlen($input)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a file to read parameters from with "--input".'));
    }

    if ($input === '-') {
      fprintf(STDERR, tsprintf("%s\n", pht('Reading input from stdin...')));
      $input_json = file_get_contents('php://stdin');
    } else {
      $input_json = Filesystem::readFile($input);
    }

    $params = phutil_json_decode($input_json);

    $result = id(new ConduitCall($method, $params))
      ->setUser($viewer)
      ->execute();

    $output = array(
      'result' => $result,
    );

    echo tsprintf(
      "%B\n",
      id(new PhutilJSON())->encodeFormatted($output));

    return 0;
  }

}
