<?php

final class PhabricatorGarbageCollectorConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Garbage Collector');
  }

  public function getDescription() {
    return pht('Configure the GC for old logs, caches, etc.');
  }

  public function getFontIcon() {
    return 'fa-trash-o';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {

    $options = array(
      'gcdaemon.ttl.herald-transcripts' => array(
        30,
        pht('Number of seconds to retain Herald transcripts for.'),
      ),
      'gcdaemon.ttl.daemon-logs' => array(
        7,
        pht('Number of seconds to retain Daemon logs for.'),
      ),
      'gcdaemon.ttl.differential-parse-cache' => array(
        14,
        pht('Number of seconds to retain Differential parse caches for.'),
      ),
      'gcdaemon.ttl.markup-cache' => array(
        30,
        pht('Number of seconds to retain Markup cache entries for.'),
      ),
      'gcdaemon.ttl.task-archive' => array(
        14,
        pht('Number of seconds to retain archived background tasks for.'),
      ),
      'gcdaemon.ttl.general-cache' => array(
        30,
        pht('Number of seconds to retain general cache entries for.'),
      ),
      'gcdaemon.ttl.conduit-logs' => array(
        180,
        pht('Number of seconds to retain Conduit call logs for.'),
      ),
    );

    $result = array();
    foreach ($options as $key => $spec) {
      list($default_days, $description) = $spec;
      $result[] = $this
        ->newOption($key, 'int', $default_days * (24 * 60 * 60))
        ->setDescription($description)
        ->addExample((7 * 24 * 60 * 60), pht('Retain for 1 week'))
        ->addExample((14 * 24 * 60 * 60), pht('Retain for 2 weeks'))
        ->addExample((30 * 24 * 60 * 60), pht('Retain for 30 days'))
        ->addExample((60 * 24 * 60 * 60), pht('Retain for 60 days'))
        ->addExample(0, pht('Retain indefinitely'));
    }
    return $result;
  }

}
