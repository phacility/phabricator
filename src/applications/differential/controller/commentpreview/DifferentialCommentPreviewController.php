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

class DifferentialCommentPreviewController extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();

    $author_phid = $request->getUser()->getPHID();

    $handles = id(new PhabricatorObjectHandleData(array($author_phid)))
      ->loadHandles();

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();

    $comment = new DifferentialComment();
    $comment->setContent($request->getStr('content'));
    $comment->setAction($request->getStr('action'));
    $comment->setAuthorPHID($author_phid);

    $view = new DifferentialRevisionCommentView();
    $view->setComment($comment);
    $view->setHandles($handles);
    $view->setMarkupEngine($engine);
    $view->setPreview(true);

    $draft = new PhabricatorDraft();
    $draft
      ->setAuthorPHID($author_phid)
      ->setDraftKey('differential-comment-'.$this->id)
      ->setDraft($comment->getContent())
      ->replace();

    return id(new AphrontAjaxResponse())
      ->setContent($view->render());
  }

}
