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

final class PhabricatorAuditPreviewController
  extends PhabricatorAuditController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $commit = id(new PhabricatorRepositoryCommit())->load($this->id);
    if (!$commit) {
      return new Aphront404Response();
    }

    $comment = id(new PhabricatorAuditComment())
      ->setActorPHID($user->getPHID())
      ->setTargetPHID($commit->getPHID())
      ->setAction($request->getStr('action'))
      ->setContent($request->getStr('content'));

    $view = id(new DiffusionCommentView())
      ->setUser($user)
      ->setComment($comment)
      ->setIsPreview(true);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $view->setHandles($handles);

    id(new PhabricatorDraft())
      ->setAuthorPHID($comment->getActorPHID())
      ->setDraftKey('diffusion-audit-'.$this->id)
      ->setDraft($comment->getContent())
      ->replace();

    return id(new AphrontAjaxResponse())
      ->setContent($view->render());
  }

}
