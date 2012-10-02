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
 * @group phriction
 */
final class PhrictionDocumentPreviewController
  extends PhrictionController {

  public function processRequest() {

    $request = $this->getRequest();
    $document = $request->getStr('document');

    $draft_key = $request->getStr('draftkey');
    if ($draft_key) {
      id(new PhabricatorDraft())
        ->setAuthorPHID($request->getUser()->getPHID())
        ->setDraftKey($draft_key)
        ->setDraft($document)
        ->replaceOrDelete();
    }

    $content_obj = new PhrictionContent();
    $content_obj->setContent($document);
    $content = $content_obj->renderContent($request->getUser());

    return id(new AphrontAjaxResponse())->setContent($content);
  }
}
