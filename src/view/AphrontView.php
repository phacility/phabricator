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

abstract class AphrontView {

  protected $children = array();

  final public function appendChild($child) {
    $this->children[] = $child;
    return $this;
  }

  final protected function renderChildren() {
    $out = array();
    foreach ($this->children as $child) {
      $out[] = $this->renderSingleView($child);
    }
    return implode('', $out);
  }

  final protected function renderSingleView($child) {
    if ($child instanceof AphrontView) {
      return $child->render();
    } else if (is_array($child)) {
      $out = array();
      foreach ($child as $element) {
        $out[] = $this->renderSingleView($element);
      }
      return implode('', $out);
    } else {
      return $child;
    }
  }

  abstract public function render();

  public function __set($name, $value) {
    phlog('Wrote to undeclared property '.get_class($this).'::$'.$name.'.');
    $this->$name = $value;
  }

}
