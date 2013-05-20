<?php

final class PhabricatorCacheManagementPurgeWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('purge')
      ->setSynopsis('Drop data from caches.')
      ->setArguments(
        array(
          array(
            'name'    => 'purge-all',
            'help'    => 'Purge all caches.',
          ),
          array(
            'name'    => 'purge-remarkup',
            'help'    => 'Purge the remarkup cache.',
          ),
          array(
            'name'    => 'purge-changeset',
            'help'    => 'Purge the Differential changeset cache.',
          ),
          array(
            'name'    => 'purge-general',
            'help'    => 'Purge the general cache.',
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
        "Specify which cache or caches to purge, or use '--purge-all'. ".
        "Available caches are: ".implode(', ', $list).". Use '--help' ".
        "for more information.");
    }

    if ($purge['remarkup']) {
      $console->writeOut("Purging remarkup cache...");
      $this->purgeRemarkupCache();
      $console->writeOut("done.\n");
    }

    if ($purge['changeset']) {
      $console->writeOut("Purging changeset cache...");
      $this->purgeChangesetCache();
      $console->writeOut("done.\n");
    }

    if ($purge['general']) {
      $console->writeOut("Purging general cache...");
      $this->purgeGeneralCache();
      $console->writeOut("done.\n");
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
