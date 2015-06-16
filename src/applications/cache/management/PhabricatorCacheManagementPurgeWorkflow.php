<?php

final class PhabricatorCacheManagementPurgeWorkflow
  extends PhabricatorCacheManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('purge')
      ->setSynopsis(pht('Drop data from caches.'))
      ->setArguments(
        array(
          array(
            'name'    => 'purge-all',
            'help'    => pht('Purge all caches.'),
          ),
          array(
            'name'    => 'purge-remarkup',
            'help'    => pht('Purge the remarkup cache.'),
          ),
          array(
            'name'    => 'purge-changeset',
            'help'    => pht('Purge the Differential changeset cache.'),
          ),
          array(
            'name'    => 'purge-general',
            'help'    => pht('Purge the general cache.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $purge_all = $args->getArg('purge-all');

    $purge = array(
      'remarkup'  => $purge_all || $args->getArg('purge-remarkup'),
      'changeset' => $purge_all || $args->getArg('purge-changeset'),
      'general'   => $purge_all || $args->getArg('purge-general'),
    );

    if (!array_filter($purge)) {
      $list = array();
      foreach ($purge as $key => $ignored) {
        $list[] = "'--purge-".$key."'";
      }

      throw new PhutilArgumentUsageException(
        pht(
          "Specify which cache or caches to purge, or use '%s'. Available ".
          "caches are: %s. Use '%s' for more information.",
          '--purge-all',
          implode(', ', $list),
          '--help'));
    }

    if ($purge['remarkup']) {
      $console->writeOut(pht('Purging remarkup cache...'));
      $this->purgeRemarkupCache();
      $console->writeOut("%s\n", pht('Done.'));
    }

    if ($purge['changeset']) {
      $console->writeOut(pht('Purging changeset cache...'));
      $this->purgeChangesetCache();
      $console->writeOut("%s\n", pht('Done.'));
    }

    if ($purge['general']) {
      $console->writeOut(pht('Purging general cache...'));
      $this->purgeGeneralCache();
      $console->writeOut("%s\n", pht('Done.'));
    }
  }

  private function purgeRemarkupCache() {
    $conn_w = id(new PhabricatorMarkupCache())->establishConnection('w');

    queryfx(
      $conn_w,
      'TRUNCATE TABLE %T',
      id(new PhabricatorMarkupCache())->getTableName());
  }

  private function purgeChangesetCache() {
    $conn_w = id(new DifferentialChangeset())->establishConnection('w');
    queryfx(
      $conn_w,
      'TRUNCATE TABLE %T',
      DifferentialChangeset::TABLE_CACHE);
  }

  private function purgeGeneralCache() {
    $conn_w = id(new PhabricatorMarkupCache())->establishConnection('w');

    queryfx(
      $conn_w,
      'TRUNCATE TABLE %T',
      'cache_general');
  }

}
