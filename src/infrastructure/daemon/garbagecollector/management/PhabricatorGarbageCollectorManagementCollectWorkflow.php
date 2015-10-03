<?php

final class PhabricatorGarbageCollectorManagementCollectWorkflow
  extends PhabricatorGarbageCollectorManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('collect')
      ->setExamples('**collect** --collector __collector__')
      ->setSynopsis(
        pht('Run a garbage collector in the foreground.'))
      ->setArguments(
        array(
          array(
            'name' => 'collector',
            'param' => 'const',
            'help' => pht(
              'Constant identifying the garbage collector to run.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $collector = $this->getCollector($args->getArg('collector'));

    echo tsprintf(
      "%s\n",
      pht('Collecting "%s" garbage...', $collector->getCollectorName()));

    $any = false;
    while (true) {
      $more = $collector->runCollector();
      if ($more) {
        $any = true;
      } else {
        break;
      }
    }

    if ($any) {
      $message = pht('Finished collecting all the garbage.');
    } else {
      $message = pht('Could not find any garbage to collect.');
    }
    echo tsprintf("\n%s\n", $message);

    return 0;
  }

}
