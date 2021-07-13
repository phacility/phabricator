<?php


class PhabricatorDiffStore {
  /** @var array<string, DifferentialDiff> */
  private array $cache;

  public function __construct() {
    $this->cache = [];
  }

  public function find(string $PHID): DifferentialDiff {
    if (array_key_exists($PHID, $this->cache)) {
      return $this->cache[$PHID];
    }

    $diff = id(new DifferentialDiffQuery())
      ->withPHIDs([$PHID])
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->needChangesets(true)
      ->executeOne();
    $this->cache[$PHID] = $diff;
    return $diff;
  }
}