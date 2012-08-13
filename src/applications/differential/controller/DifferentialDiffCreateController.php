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

final class DifferentialDiffCreateController extends DifferentialController {

  public function processRequest() {

    $request = $this->getRequest();

    if ($request->isFormPost()) {
      $diff = null;
      try {
        $diff = PhabricatorFile::readUploadedFileData($_FILES['diff-file']);
      } catch (Exception $ex) {
        $diff = $request->getStr('diff');
      }

      $call = new ConduitCall(
        'differential.createrawdiff',
        array(
          'diff' => $diff,
          ));
      $call->setUser($request->getUser());
      $result = $call->execute();

      $path = id(new PhutilURI($result['uri']))->getPath();
      return id(new AphrontRedirectResponse())->setURI($path);
    }

    $form = new AphrontFormView();
    $arcanist_href = PhabricatorEnv::getDoclink(
      'article/Arcanist_User_Guide.html');
    $arcanist_link = phutil_render_tag(
      'a',
      array(
        'href' => $arcanist_href,
        'target' => '_blank',
      ),
      'Arcanist');
    $form
      ->setAction('/differential/diff/create/')
      ->setEncType('multipart/form-data')
      ->setUser($request->getUser())
      ->appendChild(
        '<p class="aphront-form-instructions">The best way to create a '.
        "Differential diff is by using $arcanist_link, but you ".
        'can also just paste a diff (e.g., from <tt>svn diff</tt> or '.
        '<tt>git diff</tt>) into this box or upload it as a file if you '.
        'really want.</p>')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Raw Diff')
          ->setName('diff')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('Raw Diff from file')
          ->setName('diff-file'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue("Create Diff \xC2\xBB"));

    $panel = new AphrontPanelView();
    $panel->setHeader('Create New Diff');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Create Diff',
      ));
  }

}
