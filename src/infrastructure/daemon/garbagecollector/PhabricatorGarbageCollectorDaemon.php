<?php

/**
 * Collects old logs and caches to reduce the amount of data stored in the
 * database.
 */
final class PhabricatorGarbageCollectorDaemon extends PhabricatorDaemon {

  public function run() {
    $collectors = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorGarbageCollector')
      ->loadObjects();

    do {
      foreach ($collectors as $name => $collector) {
        $more_garbage = false;
        do {
          if ($more_garbage) {
            $this->log(pht('Collecting more garbage with "%s".', $name));
          } else {
            $this->log(pht('Collecting garbage with "%s".', $name));
          }

          $more_garbage = $collector->collectGarbage();
          $this->stillWorking();
        } while ($more_garbage);
      }

      // We made it to the end of the run cycle of every GC, so we're more or
      // less caught up. Ease off the GC loop so we don't keep doing table
      // scans just to delete a handful of rows; wake up in a few hours.
      $this->log(pht('All caught up, waiting for more garbage.'));
      $this->sleep(4 * (60 * 60));
    } while (true);

  }

}
