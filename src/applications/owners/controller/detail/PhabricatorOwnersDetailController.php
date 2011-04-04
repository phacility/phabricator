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

class PhabricatorOwnersDetailController extends PhabricatorOwnersController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $package = id(new PhabricatorOwnersPackage())->load($this->id);
      if (!$package) {
        return new Aphront404Response();
      }
    } else {
      $package = new PhabricatorOwnersPackage();
      $package->setPrimaryOwnerPHID($user->getPHID());
    }

    $e_name = true;
    $e_primary = true;


    $token_primary_owner = array();
    $token_all_owners = array();

    $title = $package->getID() ? 'Edit Package' : 'New Package';

    $repos = id(new PhabricatorRepository())->loadAll();

    $default_paths = array();
    foreach ($repos as $repo) {
      $default_path = $repo->getDetail('default-owners-path');
      if ($default_path) {
        $default_paths[$repo->getPHID()] = $default_path;
      }
    }

    $repos = mpull($repos, 'getCallsign', 'getPHID');

    $template = new AphrontTypeaheadTemplateView();
    $template = $template->render();


    Javelin::initBehavior(
      'owners-path-editor',
      array(
        'root'                => 'path-editor',
        'table'               => 'paths',
        'add_button'          => 'addpath',
        'repositories'        => $repos,
        'input_template'      => $template,
        'path_refs'           => array(),

        'completeURI'         => '/diffusion/services/path/complete/',
        'validateURI'         => '/diffusion/services/path/validate/',

        'repositoryDefaultPaths' => $default_paths,
      ));

    require_celerity_resource('owners-path-editor-css');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($package->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setLabel('Primary Owner')
          ->setName('primary')
          ->setLimit(1)
          ->setValue($token_primary_owner)
          ->setError($e_primary))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setLabel('Owners')
          ->setName('owners')
          ->setValue($token_all_owners)
          ->setError($e_primary))
      ->appendChild(
        '<h1>Paths</h1>'.
        '<div class="aphront-form-inset" id="path-editor">'.
          '<div style="float: right;">'.
            javelin_render_tag(
              'a',
              array(
                'href' => '#',
                'class' => 'button green',
                'sigil' => 'addpath',
                'mustcapture' => true,
              ),
              'Add New Path').
          '</div>'.
          '<p>Specify the files and directories which comprise this '.
          'package.</p>'.
          '<div style="clear: both;"></div>'.
          javelin_render_tag(
            'table',
            array(
              'class' => 'owners-path-editor-table',
              'sigil' => 'paths',
            ),
            '').
        '</div>')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Description')
          ->setName('description')
          ->setValue($package->getDescription()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Package'));

    $panel = new AphrontPanelView();
    $panel->setHeader($title);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => $title,
      ));
  }

}
