<?php

final class ManiphestLegacyTransactionQuery {

  public static function loadByTasks(
    PhabricatorUser $viewer,
    array $tasks) {

    return id(new ManiphestTransaction())->loadAllWhere(
      'taskID IN (%Ld) ORDER BY id ASC',
      mpull($tasks, 'getID'));
  }

  public static function loadByTask(
    PhabricatorUser $viewer,
    ManiphestTask $task) {

    return self::loadByTasks($viewer, array($task));
  }

  public static function loadByTransactionID(
    PhabricatorUser $viewer,
    $xaction_id) {

    return id(new ManiphestTransaction())->load($xaction_id);
  }


}
