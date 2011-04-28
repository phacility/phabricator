<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
