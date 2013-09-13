<?php

/**
 * @group maniphest
 */
final class ManiphestTaskPriority extends ManiphestConstants {

  const PRIORITY_UNBREAK_NOW  = 100;
  const PRIORITY_TRIAGE       = 90;
  const PRIORITY_HIGH         = 80;
  const PRIORITY_NORMAL       = 50;
  const PRIORITY_LOW          = 25;
  const PRIORITY_WISH         = 0;

  /**
   * Get the priorities and their full descriptions.
   *
   * @return  map Priorities to descriptions.
   */
  public static function getTaskPriorityMap() {
    return array(
      self::PRIORITY_UNBREAK_NOW  => 'Unbreak Now!',
      self::PRIORITY_TRIAGE       => 'Needs Triage',
      self::PRIORITY_HIGH         => 'High',
      self::PRIORITY_NORMAL       => 'Normal',
      self::PRIORITY_LOW          => 'Low',
      self::PRIORITY_WISH         => 'Wishlist',
    );
  }

  /**
   * Get the priorities and their related short (one-word) descriptions.
   *
   * @return  map Priorities to brief descriptions.
   */
  public static function getTaskBriefPriorityMap() {
    return array(
      self::PRIORITY_UNBREAK_NOW  => 'Unbreak!',
      self::PRIORITY_TRIAGE       => 'Triage',
      self::PRIORITY_HIGH         => 'High',
      self::PRIORITY_NORMAL       => 'Normal',
      self::PRIORITY_LOW          => 'Low',
      self::PRIORITY_WISH         => 'Wish',
    );
  }

  /**
   * Return the default priority for this instance of Phabricator.
   *
   * @return int The value of the default priority constant.
   */
  public static function getDefaultPriority() {
    return PhabricatorEnv::getEnvConfig('maniphest.default-priority');
  }

  /**
   * Retrieve the full name of the priority level provided.
   *
   * @param   int     A priority level.
   * @return  string  The priority name if the level is a valid one,
   *                  or `???` if it is not.
   */
  public static function getTaskPriorityName($priority) {
    return idx(self::getTaskPriorityMap(), $priority, '???');
  }

}
