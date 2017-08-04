<?php

final class DifferentialRevisionStatus extends Phobject {

  const NEEDS_REVIEW = 'needs-review';
  const NEEDS_REVISION = 'needs-revision';
  const CHANGES_PLANNED = 'changes-planned';
  const ACCEPTED = 'accepted';
  const PUBLISHED = 'published';
  const ABANDONED = 'abandoned';

  private $key;
  private $spec = array();

  public function getIcon() {
    return idx($this->spec, 'icon');
  }

  public function getIconColor() {
    return idx($this->spec, 'color.icon', 'bluegrey');
  }

  public function getTagColor() {
    return idx($this->spec, 'color.tag', 'bluegrey');
  }

  public function getDisplayName() {
    return idx($this->spec, 'name');
  }

  public function isClosedStatus() {
    return idx($this->spec, 'closed');
  }

  public function isAbandoned() {
    return ($this->key === self::ABANDONED);
  }

  public function isAccepted() {
    return ($this->key === self::ACCEPTED);
  }

  public function isNeedsReview() {
    return ($this->key === self::NEEDS_REVIEW);
  }

  public static function newForLegacyStatus($legacy_status) {
    $result = new self();

    $map = self::getMap();
    foreach ($map as $key => $spec) {
      if (!isset($spec['legacy'])) {
        continue;
      }

      if ($spec['legacy'] != $legacy_status) {
        continue;
      }

      $result->key = $key;
      $result->spec = $spec;
      break;
    }

    return $result;
  }

  private static function getMap() {
    $close_on_accept = PhabricatorEnv::getEnvConfig(
      'differential.close-on-accept');

    return array(
      self::NEEDS_REVIEW => array(
        'name' => pht('Needs Review'),
        'legacy' => 0,
        'icon' => 'fa-code',
        'closed' => false,
        'color.icon' => 'grey',
        'color.tag' => 'bluegrey',
        'color.ansi' => 'magenta',
      ),
      self::NEEDS_REVISION => array(
        'name' => pht('Needs Revision'),
        'legacy' => 1,
        'icon' => 'fa-refresh',
        'closed' => false,
        'color.icon' => 'red',
        'color.tag' => 'red',
        'color.ansi' => 'red',
      ),
      self::CHANGES_PLANNED => array(
        'name' => pht('Changes Planned'),
        'legacy' => 5,
        'icon' => 'fa-headphones',
        'closed' => false,
        'color.icon' => 'red',
        'color.tag' => 'red',
        'color.ansi' => 'red',
      ),
      self::ACCEPTED => array(
        'name' => pht('Accepted'),
        'legacy' => 2,
        'icon' => 'fa-check',
        'closed' => $close_on_accept,
        'color.icon' => 'green',
        'color.tag' => 'green',
        'color.ansi' => 'green',
      ),
      self::PUBLISHED => array(
        'name' => pht('Closed'),
        'legacy' => 3,
        'icon' => 'fa-check-square-o',
        'closed' => true,
        'color.icon' => 'black',
        'color.tag' => 'indigo',
        'color.ansi' => 'cyan',
      ),
      self::ABANDONED => array(
        'name' => pht('Abandoned'),
        'legacy' => 4,
        'icon' => 'fa-plane',
        'closed' => true,
        'color.icon' => 'black',
        'color.tag' => 'indigo',
        'color.ansi' => null,
      ),
    );
  }

  public static function getClosedStatuses() {
    $statuses = array(
      ArcanistDifferentialRevisionStatus::CLOSED,
      ArcanistDifferentialRevisionStatus::ABANDONED,
    );

    if (PhabricatorEnv::getEnvConfig('differential.close-on-accept')) {
      $statuses[] = ArcanistDifferentialRevisionStatus::ACCEPTED;
    }

    return $statuses;
  }

  public static function getOpenStatuses() {
    return array_diff(self::getAllStatuses(), self::getClosedStatuses());
  }

  public static function getAllStatuses() {
    return array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVIEW,
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
      ArcanistDifferentialRevisionStatus::CHANGES_PLANNED,
      ArcanistDifferentialRevisionStatus::ACCEPTED,
      ArcanistDifferentialRevisionStatus::CLOSED,
      ArcanistDifferentialRevisionStatus::ABANDONED,
      ArcanistDifferentialRevisionStatus::IN_PREPARATION,
    );
  }

}
