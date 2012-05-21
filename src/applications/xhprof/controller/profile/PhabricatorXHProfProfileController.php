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

final class PhabricatorXHProfProfileController
  extends PhabricatorXHProfController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $this->phid);

    if (!$file) {
      return new Aphront404Response();
    }

    $data = $file->loadFileData();
    $data = unserialize($data);
    if (!$data) {
      throw new Exception("Failed to unserialize XHProf profile!");
    }

    $request = $this->getRequest();
    $symbol = $request->getStr('symbol');

    $is_framed = $request->getBool('frame');

    if ($symbol) {
      $view = new PhabricatorXHProfProfileSymbolView();
      $view->setSymbol($symbol);
    } else {
      $view = new PhabricatorXHProfProfileTopLevelView();
      $view->setFile($file);
      $view->setLimit(100);
    }

    $view->setBaseURI($request->getRequestURI()->getPath());
    $view->setIsFramed($is_framed);
    $view->setProfileData($data);

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title' => 'Profile',
        'frame' => $is_framed,
      ));
  }
}
