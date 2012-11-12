<?php

abstract class DifferentialTasksAttacher {
  /**
   * Implementation of this function should attach given tasks to
   * the given revision. The function is called when 'arc' has task
   * ids defined in the commit message.
   */
  abstract public function attachTasksToRevision(
    $user_phid,
    DifferentialRevision $revision,
    array $task_ids);

  /**
   * This method will be called with a task and its original and new
   * associated revisions. Implementation of this method should update
   * the affected revisions to maintain the new associations.
   */
  abstract public function updateTaskRevisionAssoc(
    $task_phid,
    array $orig_rev_phids,
    array $new_rev_phids);

}
