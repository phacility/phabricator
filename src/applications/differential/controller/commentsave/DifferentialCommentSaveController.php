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

class DifferentialCommentSaveController extends DifferentialController {

  public function processRequest() {
    $request = $this->getRequest();
    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $revision_id = $request->getInt('revision_id');
    $revision = id(new DifferentialRevision())->load($revision_id);
    if (!$revision) {
      return new Aphront400Response();
    }

    $comment    = $request->getStr('comment');
    $action     = $request->getStr('action');
    $reviewers  = $request->getArr('reviewers');
    $ccs        = $request->getArr('ccs');

    $editor = new DifferentialCommentEditor(
      $revision,
      $request->getUser()->getPHID(),
      $action);

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $editor
      ->setMessage($comment)
      ->setContentSource($content_source)
      ->setAttachInlineComments(true)
      ->setAddCC($action != DifferentialAction::ACTION_RESIGN)
      ->setAddedReviewers($reviewers)
      ->setAddedCCs($ccs)
      ->save();

    // TODO: Diff change detection?

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $request->getUser()->getPHID(),
      'differential-comment-'.$revision->getID());
    if ($draft) {
      $draft->delete();
    }

    return id(new AphrontRedirectResponse())
      ->setURI('/D'.$revision->getID());
  }

}
