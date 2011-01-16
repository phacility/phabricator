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

class AphrontFormSelectControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-select';
  }

  private $options;

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  protected function renderInput() {
    $options = array();
    foreach ($this->getOptions() as $value => $label) {
      $options[] = phutil_render_tag(
        'option',
        array(
          'selected' => ($value == $this->getValue()) ? 'selected' : null,
          'value'    => $value,
        ),
        phutil_escape_html($label));
    }

    return phutil_render_tag(
      'select',
      array(
        'name'    => $this->getName(),
      ),
      implode("\n", $options));
  }

}
