<?php

final class ReleephSeverityFieldSpecification
  extends ReleephLevelFieldSpecification {

  const HOTFIX  = 'HOTFIX';
  const RELEASE = 'RELEASE';

  public function getName() {
    return 'Severity';
  }

  public function getStorageKey() {
    return 'releeph:severity';
  }

  public function getLevels() {
    return array(
      self::HOTFIX,
      self::RELEASE,
    );
  }

  public function getDefaultLevel() {
    return self::RELEASE;
  }

  public function getNameForLevel($level) {
    static $names = array(
      self::HOTFIX  => 'HOTFIX',
      self::RELEASE => 'RELEASE',
    );
    return idx($names, $level, $level);
  }

  public function getDescriptionForLevel($level) {
    static $descriptions = array(
      self::HOTFIX =>
        'Needs merging and fixing right now.',
      self::RELEASE =>
        'Required for the currently rolling release.',
    );
    return idx($descriptions, $level);
  }

}
