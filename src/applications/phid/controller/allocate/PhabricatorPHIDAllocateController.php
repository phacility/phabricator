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

class PhabricatorPHIDAllocateController
  extends PhabricatorPHIDController {

  public function processRequest() {

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $type = $request->getStr('type');
      $phid = PhabricatorPHID::generateNewPHID($type);

      return id(new AphrontRedirectResponse())
        ->setURI('/phid/?phid='.phutil_escape_uri($phid));
    }

    $types = id(new PhabricatorPHIDType())->loadAll();

    $options = array();
    foreach ($types as $type) {
      $options[$type->getType()] = $type->getType().': '.$type->getName();
    }
    asort($options);

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    $form->setAction('/phid/new/');

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('PHID Type')
          ->setName('type')
          ->setOptions($options))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Allocate')
          ->addCancelButton('/phid/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Allocate New PHID');

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($panel),
      array(
        'title' => 'Allocate New PHID',
      ));
  }

}
