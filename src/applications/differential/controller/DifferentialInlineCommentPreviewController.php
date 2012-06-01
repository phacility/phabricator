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

final class DifferentialInlineCommentPreviewController
  extends DifferentialController {

  private $revisionID;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    // TODO: This is a reasonable approximation of the feature as it exists
    // in Facebook trunk but we should probably pull filename data, sort these,
    // figure out next/prev/edit/delete, deal with out-of-date inlines, etc.

    $inlines = id(new DifferentialInlineComment())->loadAllWhere(
      'authorPHID = %s AND revisionID = %d AND commentID IS NULL',
      $user->getPHID(),
      $this->revisionID);

    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();

    $phids = array($user->getPHID());
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $views = array();
    foreach ($inlines as $inline) {
      $view = new DifferentialInlineCommentView();
      $view->setInlineComment($inline);
      $view->setMarkupEngine($engine);
      $view->setHandles($handles);
      $view->setEditable(false);
      $view->setPreview(true);
      $views[] = $view->render();
    }
    $views = implode("\n", $views);

    return id(new AphrontAjaxResponse())
      ->setContent($views);
  }


}
