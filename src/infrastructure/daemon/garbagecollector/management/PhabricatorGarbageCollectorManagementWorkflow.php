<?php

abstract class PhabricatorGarbageCollectorManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function getCollector($const) {
    $collectors = PhabricatorGarbageCollector::getAllCollectors();

    $collector_list = array_keys($collectors);
    sort($collector_list);
    $collector_list = implode(', ', $collector_list);

    if (!$const) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a collector with "%s". Valid collectors are: %s.',
          '--collector',
          $collector_list));
    }

    if (empty($collectors[$const])) {
      throw new PhutilArgumentUsageException(
        pht(
          'No such collector "%s". Choose a valid collector: %s.',
          $const,
          $collector_list));
    }

    return $collectors[$const];
  }

}
