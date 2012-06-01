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

final class PhabricatorXHPASTViewRunController
  extends PhabricatorXHPASTViewController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $source = $request->getStr('source');

      $future = xhpast_get_parser_future($source);
      $resolved = $future->resolve();

      // This is just to let it throw exceptions if stuff is broken.
      $parse_tree = XHPASTTree::newFromDataAndResolvedExecFuture(
        $source,
        $resolved);

      list($err, $stdout, $stderr) = $resolved;

      $storage_tree = new PhabricatorXHPASTViewParseTree();
      $storage_tree->setInput($source);
      $storage_tree->setStdout($stdout);
      $storage_tree->setAuthorPHID($user->getPHID());
      $storage_tree->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/xhpast/view/'.$storage_tree->getID().'/');
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Source')
          ->setName('source')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Parse'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Generate XHP AST');
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'XHPAST View',
      ));
  }

}
