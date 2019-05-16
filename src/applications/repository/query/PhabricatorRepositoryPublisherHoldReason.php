<?php

final class PhabricatorRepositoryPublisherHoldReason
  extends Phobject {

  private $key;
  private $spec;

  public static function newForHoldKey($key) {
    $spec = self::getSpecForHoldKey($key);

    $hold = new self();
    $hold->key = $key;
    $hold->spec = $spec;

    return $hold;
  }

  private static function getSpecForHoldKey($key) {
    $specs = self::getHoldReasonSpecs();

    $spec = idx($specs, $key);

    if (!$spec) {
      $spec = array(
        'name' => pht('Unknown Hold ("%s")', $key),
      );
    }

    return $spec;
  }

  public function getName() {
    return $this->getProperty('name');
  }

  public function getSummary() {
    return $this->getProperty('summary');
  }

  private function getProperty($key, $default = null) {
    return idx($this->spec, $key, $default);
  }

  private static function getHoldReasonSpecs() {
    $map = array(
      PhabricatorRepositoryPublisher::HOLD_IMPORTING => array(
        'name' => pht('Repository Importing'),
        'summary' => pht('This repository is still importing.'),
      ),
      PhabricatorRepositoryPublisher::HOLD_PUBLISHING_DISABLED => array(
        'name' => pht('Publishing Disabled'),
        'summary' => pht('All publishing is disabled for this repository.'),
      ),
      PhabricatorRepositoryPublisher::HOLD_NOT_REACHABLE_FROM_PERMANENT_REF =>
      array(
        'name' => pht('Not On Permanent Ref'),
        'summary' => pht(
          'This commit is not an ancestor of any permanent ref.'),
      ),
      PhabricatorRepositoryPublisher::HOLD_REF_NOT_BRANCH => array(
        'name' => pht('Not a Branch'),
        'summary' => pht('This ref is not a branch.'),
      ),
      PhabricatorRepositoryPublisher::HOLD_UNTRACKED => array(
        'name' => pht('Untracked Ref'),
        'summary' => pht('This ref is configured as untracked.'),
      ),
      PhabricatorRepositoryPublisher::HOLD_NOT_PERMANENT_REF => array(
        'name' => pht('Not a Permanent Ref'),
        'summary' => pht('This ref is not configured as a permanent ref.'),
      ),
    );

    return $map;
  }

}
