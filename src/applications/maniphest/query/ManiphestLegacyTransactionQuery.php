<?php

final class ManiphestLegacyTransactionQuery {

  public static function loadByTasks(
    PhabricatorUser $viewer,
    array $tasks) {

    $xactions = id(new ManiphestTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(mpull($tasks, 'getPHID'))
      ->needComments(true)
      ->execute();

    foreach ($xactions as $key => $xaction) {
      $xactions[$key] = ManiphestTransaction::newFromModernTransaction(
        $xaction);
    }

    return $xactions;
  }

  public static function loadByTask(
    PhabricatorUser $viewer,
    ManiphestTask $task) {

    return self::loadByTasks($viewer, array($task));
  }

  public static function loadByTransactionID(
    PhabricatorUser $viewer,
    $xaction_id) {

    $xaction = id(new ManiphestTransactionPro())->load($xaction_id);
    if (!$xaction) {
      return null;
    }

    $xaction = id(new ManiphestTransactionQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xaction->getPHID()))
      ->needComments(true)
      ->executeOne();

    if ($xaction) {
      $xaction = ManiphestTransaction::newFromModernTransaction($xaction);
    }

    return $xaction;
  }


}
