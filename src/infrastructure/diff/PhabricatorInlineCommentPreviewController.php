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

abstract class PhabricatorInlineCommentPreviewController
  extends PhabricatorController {

  abstract protected function loadInlineComments();

  public function processRequest() {
    $request = $this->getRequest();
    $user    = $request->getUser();

    $inlines = $this->loadInlineComments();
    assert_instances_of($inlines, 'PhabricatorInlineCommentInterface');

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }
    $engine->process();

    $phids = array($user->getPHID());
    $handles = $this->loadViewerHandles($phids);

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
