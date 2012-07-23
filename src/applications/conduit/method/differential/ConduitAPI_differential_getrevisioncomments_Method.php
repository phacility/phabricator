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
final class ConduitAPI_differential_getrevisioncomments_Method
  extends ConduitAPI_differential_Method {

  public function getMethodDescription() {
    return "Retrieve Differential Revision Comments.";
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
      'inlines' => 'optional bool',
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

    $with_inlines = $request->getValue('inlines');
    if ($with_inlines) {
      $inlines = id(new DifferentialInlineComment())->loadAllWhere(
        'revisionID IN (%Ld)',
        $revision_ids);
      $changesets = array();
      if ($inlines) {
        $changesets = id(new DifferentialChangeset())->loadAllWhere(
          'id IN (%Ld)',
          array_unique(mpull($inlines, 'getChangesetID')));
        $inlines = mgroup($inlines, 'getCommentID');
      }
    }

    foreach ($comments as $comment) {
      $revision_id = $comment->getRevisionID();
      $result = array(
        'revisionID'  => $revision_id,
        'action'      => $comment->getAction(),
        'authorPHID'  => $comment->getAuthorPHID(),
        'dateCreated' => $comment->getDateCreated(),
        'content'     => $comment->getContent(),
      );

      if ($with_inlines) {
        $result['inlines'] = array();
        foreach (idx($inlines, $comment->getID(), array()) as $inline) {
          $changeset = idx($changesets, $inline->getChangesetID());
          $result['inlines'][] = $this->buildInlineInfoDictionary(
            $inline,
            $changeset);
        }
        // TODO: Put synthetic inlines without an attached comment somewhere.
      }

      $results[$revision_id][] = $result;
    }

    return $results;
  }
}
