<?php

final class PhabricatorCacheManagementPurgeWorkflow
  extends PhabricatorCacheManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('purge')
      ->setSynopsis(pht('Drop data from readthrough caches.'))
      ->setArguments(
        array(
          array(
            'name' => 'all',
            'help' => pht('Purge all caches.'),
          ),
          array(
            'name' => 'caches',
            'param' => 'keys',
            'help' => pht('Purge a specific set of caches.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $all_purgers = PhabricatorCachePurger::getAllPurgers();

    $is_all = $args->getArg('all');
    $key_list = $args->getArg('caches');

    if ($is_all && strlen($key_list)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either "--all" or "--caches", not both.'));
    } else if (!$is_all && !strlen($key_list)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Select caches to purge with "--all" or "--caches". Available '.
          'caches are: %s.',
          implode(', ', array_keys($all_purgers))));
    }

    if ($is_all) {
      $purgers = $all_purgers;
    } else {
      $key_list = preg_split('/[\s,]+/', $key_list);
      $purgers = array();
      foreach ($key_list as $key) {
        if (isset($all_purgers[$key])) {
          $purgers[$key] = $all_purgers[$key];
        } else {
          throw new PhutilArgumentUsageException(
            pht(
              'Cache purger "%s" is not recognized. Available caches '.
              'are: %s.',
              $key,
              implode(', ', array_keys($all_purgers))));
        }
      }
      if (!$purgers) {
        throw new PhutilArgumentUsageException(
          pht(
            'When using "--caches", you must select at least one valid '.
            'cache to purge.'));
      }
    }

    $viewer = $this->getViewer();

    foreach ($purgers as $key => $purger) {
      $purger->setViewer($viewer);

      echo tsprintf(
        "%s\n",
        pht(
          'Purging "%s" cache...',
          $key));

      $purger->purgeCache();
    }

    return 0;
  }

}
