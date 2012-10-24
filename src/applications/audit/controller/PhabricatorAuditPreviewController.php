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

    $action = $request->getStr('action');

    $comment = id(new PhabricatorAuditComment())
      ->setActorPHID($user->getPHID())
      ->setTargetPHID($commit->getPHID())
      ->setAction($action)
      ->setContent($request->getStr('content'));

    $phids = array(
      $user->getPHID(),
      $commit->getPHID(),
    );

    $auditors = $request->getStrList('auditors');
    if ($action == PhabricatorAuditActionConstants::ADD_AUDITORS && $auditors) {
      $comment->setMetadata(array(
        PhabricatorAuditComment::METADATA_ADDED_AUDITORS => $auditors));
      $phids = array_merge($phids, $auditors);
    }

    $ccs = $request->getStrList('ccs');
    if ($action == PhabricatorAuditActionConstants::ADD_CCS && $ccs) {
      $comment->setMetadata(array(
        PhabricatorAuditComment::METADATA_ADDED_CCS => $ccs));
      $phids = array_merge($phids, $ccs);
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject(
      $comment,
      PhabricatorAuditComment::MARKUP_FIELD_BODY);
    $engine->process();

    $view = id(new DiffusionCommentView())
      ->setMarkupEngine($engine)
      ->setUser($user)
      ->setComment($comment)
      ->setIsPreview(true);

    $phids = array_merge($phids, $view->getRequiredHandlePHIDs());

    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    id(new PhabricatorDraft())
      ->setAuthorPHID($comment->getActorPHID())
      ->setDraftKey('diffusion-audit-'.$this->id)
      ->setDraft($comment->getContent())
      ->replaceOrDelete();

    return id(new AphrontAjaxResponse())
      ->setContent($view->render());
  }

}
