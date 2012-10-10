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
final class ConduitAPI_phriction_edit_Method
  extends ConduitAPI_phriction_Method {

  public function getMethodDescription() {
    return "Update a Phriction document.";
  }

  public function defineParamTypes() {
    return array(
      'slug'          => 'required string',
      'title'         => 'optional string',
      'content'       => 'optional string',
      'description'   => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');

    $editor = id(PhrictionDocumentEditor::newForSlug($slug))
      ->setActor($request->getUser())
      ->setTitle($request->getValue('title'))
      ->setContent($request->getValue('content'))
      ->setDescription($request->getvalue('description'))
      ->save();

    return $this->buildDocumentInfoDictionary($editor->getDocument());
  }

}
