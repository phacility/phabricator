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

/**
 * @group conduit
 */
final class PhabricatorConduitConsoleController
  extends PhabricatorConduitController {

  private $method;

  public function willProcessRequest(array $data) {
    $this->method = idx($data, 'method');
  }

  public function processRequest() {

    $request = $this->getRequest();

    $methods = $this->getAllMethods();
    if (empty($methods[$this->method])) {
      $this->method = key($methods);
    }
    $this->setFilter('method/'.$this->method);

    $method_class = $methods[$this->method];
    PhutilSymbolLoader::loadClass($method_class);
    $method_object = newv($method_class, array());


    $error_description = array();
    $error_types = $method_object->defineErrorTypes();
    if ($error_types) {
      $error_description[] = '<ul>';
      foreach ($error_types as $error => $meaning) {
        $error_description[] =
          '<li>'.
            '<strong>'.phutil_escape_html($error).':</strong> '.
            phutil_escape_html($meaning).
          '</li>';
      }
      $error_description[] = '</ul>';
      $error_description = implode("\n", $error_description);
    } else {
      $error_description = "This method does not raise any specific errors.";
    }

    $form = new AphrontFormView();
    $form
      ->setUser($request->getUser())
      ->setAction('/api/'.$this->method)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Description')
          ->setValue($method_object->getMethodDescription()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Returns')
          ->setValue($method_object->defineReturnType()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Errors')
          ->setValue($error_description))
      ->appendChild(
        '<p class="aphront-form-instructions">Enter parameters using '.
        '<strong>JSON</strong>. For instance, to enter a list, type: '.
        '<tt>["apple", "banana", "cherry"]</tt>');

    $params = $method_object->defineParamTypes();
    foreach ($params as $param => $desc) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel($param)
          ->setName("params[{$param}]")
          ->setCaption(phutil_escape_html($desc)));
    }

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Output Format')
          ->setName('output')
          ->setOptions(
            array(
              'human' => 'Human Readable',
              'json'  => 'JSON',
            )))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Call Method'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Conduit API: '.phutil_escape_html($this->method));
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);

    return $this->buildStandardPageResponse(
      array($panel),
      array(
        'title' => 'Conduit Console',
      ));
  }

  private function getAllMethods() {
    $classes = $this->getAllMethodImplementationClasses();
    $methods = array();
    foreach ($classes as $class) {
      $name = ConduitAPIMethod::getAPIMethodNameFromClassName($class);
      $methods[$name] = $class;
    }
    return $methods;
  }
}
