<?php

/**
 * This is a DAO for the Task -> Project table, which denormalizes the
 * relationship between tasks and projects into a link table so it can be
 * efficiently queried. This table is not authoritative; the projectPHIDs field
 * of ManiphestTask is. The rows in this table are regenerated when transactions
 * are applied to tasks which affected their associated projects.
 *
 * @group maniphest
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
    $dao = new ManiphestTaskProject();
    $conn = $dao->establishConnection('w');

    $sql = array();
    foreach ($task->getProjectPHIDs() as $project_phid) {
      $sql[] = qsprintf(
        $conn,
        '(%s, %s)',
        $task->getPHID(),
        $project_phid);
    }

    queryfx(
      $conn,
      'DELETE FROM %T WHERE taskPHID = %s',
      $dao->getTableName(),
      $task->getPHID());
    if ($sql) {
      queryfx(
        $conn,
        'INSERT INTO %T (taskPHID, projectPHID) VALUES %Q',
        $dao->getTableName(),
        implode(', ', $sql));
    }
  }

}
