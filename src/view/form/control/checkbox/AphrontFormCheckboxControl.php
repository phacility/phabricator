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

final class AphrontFormCheckboxControl extends AphrontFormControl {

  private $boxes = array();

  public function addCheckbox($name, $value, $label, $checked = false) {
    $this->boxes[] = array(
      'name'    => $name,
      'value'   => $value,
      'label'   => $label,
      'checked' => $checked,
    );
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-checkbox';
  }

  protected function renderInput() {
    $rows = array();
    foreach ($this->boxes as $box) {
      $id = celerity_generate_unique_node_id();
      $checkbox = phutil_render_tag(
        'input',
        array(
          'id' => $id,
          'type' => 'checkbox',
          'name' => $box['name'],
          'value' => $box['value'],
          'checked' => $box['checked'] ? 'checked' : null,
          'disabled' => $this->getDisabled() ? 'disabled' : null,
        ));
      $label = phutil_render_tag(
        'label',
        array(
          'for' => $id,
        ),
        phutil_escape_html($box['label']));
      $rows[] =
        '<tr>'.
          '<td>'.$checkbox.'</td>'.
          '<th>'.$label.'</th>'.
        '</tr>';
    }
    return
      '<table class="aphront-form-control-checkbox-layout">'.
        implode("\n", $rows).
      '</table>';
  }

}
