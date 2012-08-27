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
final class ConduitAPI_paste_info_Method extends ConduitAPI_paste_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Replaced by 'paste.query'.";
  }

  public function getMethodDescription() {
    return "Retrieve an array of information about a paste.";
  }

  public function defineParamTypes() {
    return array(
      'paste_id' => 'required id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_PASTE' => 'No such paste exists',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $paste_id = $request->getValue('paste_id');
    $paste = id(new PhabricatorPasteQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($paste_id))
      ->needContent(true)
      ->executeOne();
    if (!$paste) {
      throw new ConduitException('ERR_BAD_PASTE');
    }
    return $this->buildPasteInfoDictionary($paste);
  }

}
