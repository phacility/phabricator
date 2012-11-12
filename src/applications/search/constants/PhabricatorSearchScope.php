<?php

/**
 * @group search
 */
final class PhabricatorSearchScope {

  const SCOPE_ALL               = 'all';
  const SCOPE_OPEN_REVISIONS    = 'open-revisions';
  const SCOPE_OPEN_TASKS        = 'open-tasks';
  const SCOPE_COMMITS           = 'commits';
  const SCOPE_WIKI              = 'wiki';
  const SCOPE_QUESTIONS         = 'questions';

  public static function getScopeOptions() {
    return array(
      self::SCOPE_ALL               => 'All Documents',
      self::SCOPE_OPEN_TASKS        => 'Open Tasks',
      self::SCOPE_WIKI              => 'Wiki Documents',
      self::SCOPE_OPEN_REVISIONS    => 'Open Revisions',
      self::SCOPE_COMMITS           => 'Commits',
      self::SCOPE_QUESTIONS         => 'Ponder Questions',
    );
  }

  public static function getScopePlaceholder($scope) {
    switch ($scope) {
      case self::SCOPE_OPEN_TASKS:
        return pht('Search Open Tasks');
      case self::SCOPE_WIKI:
        return pht('Search Wiki Documents');
      case self::SCOPE_OPEN_REVISIONS:
        return pht('Search Open Revisions');
      case self::SCOPE_COMMITS:
        return pht('Search Commits');
      default:
        return pht('Search');
    }
  }

}
