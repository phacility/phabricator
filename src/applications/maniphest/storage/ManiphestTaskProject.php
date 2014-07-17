<?php

/**
 * This is a DAO for the Task -> Project table, which denormalizes the
 * relationship between tasks and projects into a link table so it can be
 * efficiently queried. This table is not authoritative; the projectPHIDs field
 * of ManiphestTask is. The rows in this table are regenerated when transactions
 * are applied to tasks which affected their associated projects.
 */
final class ManiphestTaskProject extends ManiphestDAO {

  protected $taskPHID;
  protected $projectPHID;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS          => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS   => false,
    );
  }

  public static function updateTaskProjects(ManiphestTask $task) {
    $edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

    $old_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $task->getPHID(),
      $edge_type);
    $new_phids = $task->getProjectPHIDs();

    $add_phids = array_diff($new_phids, $old_phids);
    $rem_phids = array_diff($old_phids, $new_phids);

    if (!$add_phids && !$rem_phids) {
      return;
    }


    $editor = new PhabricatorEdgeEditor();
    foreach ($add_phids as $phid) {
      $editor->addEdge($task->getPHID(), $edge_type, $phid);
    }
    foreach ($rem_phids as $phid) {
      $editor->remEdge($task->getPHID(), $edge_type, $phid);
    }
    $editor->save();
  }

}
