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

final class DrydockResourceAllocateController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $resource = new DrydockResource();

    $json = new PhutilJSON();

    $err_attributes = true;
    $err_capabilities = true;

    $json_attributes = $json->encodeFormatted($resource->getAttributes());
    $json_capabilities = $json->encodeFormatted($resource->getCapabilities());

    $errors = array();

    if ($request->isFormPost()) {
      $raw_attributes = $request->getStr('attributes');
      $attributes = json_decode($raw_attributes, true);
      if (!is_array($attributes)) {
        $err_attributes = 'Invalid';
        $errors[] = 'Enter attributes as a valid JSON object.';
        $json_attributes = $raw_attributes;
      } else {
        $resource->setAttributes($attributes);
        $json_attributes = $json->encodeFormatted($attributes);
        $err_attributes = null;
      }

      $raw_capabilities = $request->getStr('capabilities');
      $capabilities = json_decode($raw_capabilities, true);
      if (!is_array($capabilities)) {
        $err_capabilities = 'Invalid';
        $errors[] = 'Enter capabilities as a valid JSON object.';
        $json_capabilities = $raw_capabilities;
      } else {
        $resource->setCapabilities($capabilities);
        $json_capabilities = $json->encodeFormatted($capabilities);
        $err_capabilities = null;
      }

      $resource->setBlueprintClass($request->getStr('blueprint'));
      $resource->setType($resource->getBlueprint()->getType());
      $resource->setOwnerPHID($user->getPHID());
      $resource->setName($request->getStr('name'));

      if (!$errors) {
        $resource->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/drydock/resource/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    }


    $blueprints = id(new PhutilSymbolLoader())
      ->setType('class')
      ->setAncestorClass('DrydockBlueprint')
      ->selectAndLoadSymbols();
    $blueprints = ipull($blueprints, 'name', 'name');
    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader('Allocate Drydock Resource');

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($resource->getName()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Blueprint')
          ->setOptions($blueprints)
          ->setName('blueprint')
          ->setValue($resource->getBlueprintClass()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Attributes')
          ->setName('attributes')
          ->setValue($json_attributes)
          ->setError($err_attributes)
          ->setCaption('Specify attributes in JSON.'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Capabilities')
          ->setName('capabilities')
          ->setValue($json_capabilities)
          ->setError($err_capabilities)
          ->setCaption('Specify capabilities in JSON.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Allocate Resource'));

    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Allocate Resource',
      ));

  }

}
