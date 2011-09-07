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

class AphrontJavelinView extends AphrontView {
  private static $renderContext = array();
  private static function peekRenderContext() {
    return nonempty(end(self::$renderContext), null);
  }

  private static function popRenderContext() {
    return array_pop(self::$renderContext);
  }

  private static function pushRenderContext($token) {
    self::$renderContext[] = $token;
  }


  private $name;
  private $parameters;
  private $celerityResource;

  public function render() {
    $id = celerity_generate_unique_node_id();
    $placeholder = "<span id={$id} />";

    require_celerity_resource($this->getCelerityResource());

    $render_context = self::peekRenderContext();
    self::pushRenderContext($id);

    Javelin::initBehavior('view-placeholder', array(
      'id' => $id,
      'view' => $this->getName(),
      'params' => $this->getParameters(),
      'children' => $this->renderChildren(),
      'trigger_id' => $render_context,
    ));

    self::popRenderContext();

    return $placeholder;
  }


  protected function getName() {
    return $this->name;
  }

  final public function setName($template_name) {
    $this->name = $template_name;
    return $this;
  }

  protected function getParameters() {
    return $this->parameters;
  }

  final public function setParameters($template_parameters) {
    $this->parameters = $template_parameters;
    return $this;
  }

  protected function getCelerityResource() {
    return $this->celerityResource;
  }

  final public function setCelerityResource($celerity_resource) {
    $this->celerityResource = $celerity_resource;
    return $this;
  }
}
