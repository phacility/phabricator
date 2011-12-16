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

class HeraldNewController extends HeraldController {

  private $type;

  public function getFilter() {
    return 'new';
  }

  public function willProcessRequest(array $data) {
    $this->type = idx($data, 'type');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $map = HeraldContentTypeConfig::getContentTypeMap();
    if (empty($map[$this->type])) {
      reset($map);
      $this->type = key($map);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/herald/rule/')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('New rule for')
          ->setName('type')
          ->setValue($this->type)
          ->setOptions($map))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Create Rule')
          ->addCancelButton('/herald/view/'.$this->type.'/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Create New Herald Rule');
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Create Herald Rule',
      ));
  }

}
