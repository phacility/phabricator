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

final class PhabricatorXHPASTViewFramesetController
  extends PhabricatorXHPASTViewController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $id = $this->id;

    $response = new AphrontWebpageResponse();
    $response->setFrameable(true);
    $response->setContent(
      '<frameset cols="33%, 34%, 33%">'.
        '<frame src="/xhpast/input/'.$id.'/" />'.
        '<frame src="/xhpast/tree/'.$id.'/" />'.
        '<frame src="/xhpast/stream/'.$id.'/" />'.
      '</frameset>');

    return $response;
  }
}
