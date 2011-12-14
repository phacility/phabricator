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

/**
 * @group conduit
 */
class ConduitAPI_differential_getrevisioncomments_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve Differential Revision Comments.";
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

    foreach ($comments as $comment) {
      $revision_id = $comment->getRevisionID();
      if (!array_key_exists($revision_id, $results)) {
        $results[$revision_id] = array();
      }
      $results[$revision_id][] = array(
        'revisionID'  => $revision_id,
        'action'      => $comment->getAction(),
        'authorPHID'  => $comment->getAuthorPHID(),
        'dateCreated' => $comment->getDateCreated(),
        'content'     => $comment->getContent(),
      );
    }

    return $results;
  }
}
