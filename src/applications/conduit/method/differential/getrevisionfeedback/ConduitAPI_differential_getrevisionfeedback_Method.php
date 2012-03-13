<?php

/*
 * Copyright 2012 Facebook, Inc.
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

/**
 * @group conduit
 */
final class ConduitAPI_differential_getrevisionfeedback_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve Differential Revision Feedback.";
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();
    $revision_ids = $request->getValue('ids');

    if (!$revision_ids) {
      return $results;
    }

    $comments = id(new DifferentialComment())->loadAllWhere(
      'revisionID IN (%Ld)',
      $revision_ids);

    // Helper dictionary to keep track of where the id/action pair is
    // stored in results array.
    $indexes = array();
    foreach ($comments as $comment) {
      $action = $comment->getAction();
      $revision_id = $comment->getRevisionID();

      if (isset($indexes[$action.$revision_id])) {
        $results[$indexes[$action.$revision_id]]['count']++;
      } else {
        $indexes[$action.$revision_id] = count($results);
        $results[] = array('id'     => $revision_id,
                           'action' => $action,
                           'count'  => 1);
      }
    }

    return $results;
  }
}
