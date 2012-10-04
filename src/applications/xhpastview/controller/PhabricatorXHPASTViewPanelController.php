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

abstract class PhabricatorXHPASTViewPanelController
  extends PhabricatorXHPASTViewController {

  private $id;
  private $storageTree;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->storageTree = id(new PhabricatorXHPASTViewParseTree())
      ->load($this->id);
    if (!$this->storageTree) {
      throw new Exception("No such AST!");
    }
  }

  protected function getStorageTree() {
    return $this->storageTree;
  }

  protected function buildXHPASTViewPanelResponse($content) {
    $content =
      '<!DOCTYPE html>'.
      '<html>'.
        '<head>'.
          '<style type="text/css">
body {
  white-space: pre;
  font: 10px "Monaco";
  cursor: pointer;
}

.token {
  padding: 2px 4px;
  margin: 2px 2px;
  border: 1px solid #bbbbbb;
  line-height: 24px;
}

ul {
  margin: 0 0 0 1em;
  padding: 0;
  list-style: none;
  line-height: 1em;
}

li {
  margin: 0;
  padding: 0;
}

li span {
  background: #dddddd;
  padding: 3px 6px;
}

          </style>'.
        '</head>'.
        '<body>'.
          $content.
        '</body>'.
      '</html>';

    $response = new AphrontWebpageResponse();
    $response->setFrameable(true);
    $response->setContent($content);
    return $response;
  }

}
