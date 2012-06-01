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

final class PhabricatorXHPASTViewStreamController
  extends PhabricatorXHPASTViewPanelController {

  public function processRequest() {
    $storage = $this->getStorageTree();
    $input = $storage->getInput();
    $stdout = $storage->getStdout();

    $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
      $input,
      array(0, $stdout, ''));

    $tokens = array();
    foreach ($tree->getRawTokenStream() as $id => $token) {
      $seq = $id;
      $name = $token->getTypeName();
      $title = "Token {$seq}: {$name}";

      $tokens[] = phutil_render_tag(
        'span',
        array(
          'title' => $title,
          'class' => 'token',
        ),
        phutil_escape_html($token->getValue()));
    }

    return $this->buildXHPASTViewPanelResponse(implode('', $tokens));
  }
}
