<?php

/**
 * @group maniphest
 */
final class ManiphestAction extends ManiphestConstants {
  /* These actions must be determined when the story
     is generated and thus are new */
  const ACTION_CREATE      = 'create';
  const ACTION_REOPEN      = 'reopen';
  const ACTION_CLOSE       = 'close';
  const ACTION_UPDATE      = 'update';
  const ACTION_ASSIGN      = 'assign';

  /* these actions are determined sufficiently by the transaction
     type and thus we use them here*/
  const ACTION_COMMENT     = ManiphestTransactionType::TYPE_NONE;
  const ACTION_CC          = ManiphestTransactionType::TYPE_CCS;
  const ACTION_PRIORITY    = ManiphestTransactionType::TYPE_PRIORITY;
  const ACTION_PROJECT     = ManiphestTransactionType::TYPE_PROJECTS;
  const ACTION_TITLE       = ManiphestTransactionType::TYPE_TITLE;
  const ACTION_DESCRIPTION = ManiphestTransactionType::TYPE_DESCRIPTION;
  const ACTION_REASSIGN    = ManiphestTransactionType::TYPE_OWNER;
  const ACTION_ATTACH      = ManiphestTransactionType::TYPE_ATTACH;
  const ACTION_EDGE        = ManiphestTransactionType::TYPE_EDGE;
  const ACTION_AUXILIARY   = ManiphestTransactionType::TYPE_AUXILIARY;

  public static function getActionPastTenseVerb($action) {
    static $map = array(
      self::ACTION_CREATE      => 'created',
      self::ACTION_CLOSE       => 'closed',
      self::ACTION_UPDATE      => 'updated',
      self::ACTION_ASSIGN      => 'assigned',
      self::ACTION_REASSIGN    => 'reassigned',
      self::ACTION_COMMENT     => 'commented on',
      self::ACTION_CC          => 'updated cc\'s of',
      self::ACTION_PRIORITY    => 'changed the priority of',
      self::ACTION_PROJECT     => 'modified projects of',
      self::ACTION_TITLE       => 'updated title of',
      self::ACTION_DESCRIPTION => 'updated description of',
      self::ACTION_ATTACH      => 'attached something to',
      self::ACTION_EDGE        => 'changed related objects of',
      self::ACTION_REOPEN      => 'reopened',
      self::ACTION_AUXILIARY   => 'updated an auxiliary field of',
    );

    return idx($map, $action, "brazenly {$action}'d");
  }

  /**
   * If a group of transactions contain several actions, select the "strongest"
   * action. For instance, a close is stronger than an update, because we want
   * to render "User U closed task T" instead of "User U updated task T" when
   * a user closes a task.
   */
  public static function selectStrongestAction(array $actions) {
    static $strengths = array(
      self::ACTION_AUXILIARY   => -1,
      self::ACTION_UPDATE      => 0,
      self::ACTION_CC          => 1,
      self::ACTION_PROJECT     => 2,
      self::ACTION_DESCRIPTION => 3,
      self::ACTION_TITLE       => 4,
      self::ACTION_ATTACH      => 5,
      self::ACTION_EDGE        => 5,
      self::ACTION_COMMENT     => 6,
      self::ACTION_PRIORITY    => 7,
      self::ACTION_REASSIGN    => 8,
      self::ACTION_ASSIGN      => 9,
      self::ACTION_REOPEN      => 10,
      self::ACTION_CREATE      => 11,
      self::ACTION_CLOSE       => 12,
    );

    $strongest = null;
    $strength = -1;
    foreach ($actions as $action) {
      if ($strengths[$action] > $strength) {
        $strength = $strengths[$action];
        $strongest = $action;
      }
    }
    return $strongest;
  }

}
