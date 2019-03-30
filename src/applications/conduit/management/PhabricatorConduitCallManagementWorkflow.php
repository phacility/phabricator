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
          array(
            'name' => 'as',
            'param' => 'username',
            'help' => pht(
              'Execute the call as the given user. (If omitted, the call will '.
              'be executed as an omnipotent user.)'),
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

    $as = $args->getArg('as');
    if (strlen($as)) {
      $actor = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withUsernames(array($as))
        ->executeOne();
      if (!$actor) {
        throw new PhutilArgumentUsageException(
          pht(
            'No such user "%s" exists.',
            $as));
      }

      // Allow inline generation of user caches for the user we're acting
      // as, since some calls may read user preferences.
      $actor->setAllowInlineCacheGeneration(true);
    } else {
      $actor = $viewer;
    }

    if ($input === '-') {
      fprintf(STDERR, tsprintf("%s\n", pht('Reading input from stdin...')));
      $input_json = file_get_contents('php://stdin');
    } else {
      $input_json = Filesystem::readFile($input);
    }

    $params = phutil_json_decode($input_json);

    $result = id(new ConduitCall($method, $params))
      ->setUser($actor)
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
