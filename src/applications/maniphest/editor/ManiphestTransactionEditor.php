<?php

/**
 * @group maniphest
 */
final class ManiphestTransactionEditor extends PhabricatorEditor {

  public function buildReplyHandler(ManiphestTask $task) {
    $handler_object = PhabricatorEnv::newObjectFromConfig(
      'metamta.maniphest.reply-handler');
    $handler_object->setMailReceiver($task);

    return $handler_object;
  }

  public static function getNextSubpriority($pri, $sub) {

    if ($sub === null) {
      $next = id(new ManiphestTask())->loadOneWhere(
        'priority = %d ORDER BY subpriority ASC LIMIT 1',
        $pri);
      if ($next) {
        return $next->getSubpriority() - ((double)(2 << 16));
      }
    } else {
      $next = id(new ManiphestTask())->loadOneWhere(
        'priority = %d AND subpriority > %s ORDER BY subpriority ASC LIMIT 1',
        $pri,
        $sub);
      if ($next) {
        return ($sub + $next->getSubpriority()) / 2;
      }
    }

    return (double)(2 << 32);
  }

}
