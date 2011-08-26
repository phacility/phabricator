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
class ConduitAPI_phriction_info_Method
  extends ConduitAPI_phriction_Method {

  public function getMethodDescription() {
    return "Retrieve information about a Phriction document.";
  }

  public function defineParamTypes() {
    return array(
      'slug' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-DOCUMENT' => 'No such document exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $slug = $request->getValue('slug');

    $doc = id(new PhrictionDocument())->loadOneWhere(
      'slug = %s',
      PhrictionDocument::normalizeSlug($slug));

    if (!$doc) {
      throw new ConduitException('ERR-BAD-DOCUMENT');
    }

    $content = id(new PhrictionContent())->load($doc->getContentID());
    $doc->attachContent($content);

    return $this->buildDocumentInfoDictionary($doc);
  }

}
