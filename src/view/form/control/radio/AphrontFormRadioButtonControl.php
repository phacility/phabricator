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

final class AphrontFormRadioButtonControl extends AphrontFormControl {

  private $buttons = array();

  public function addButton($value, $label, $caption) {
    $this->buttons[] = array(
      'value'   => $value,
      'label'   => $label,
      'caption' => $caption,
    );
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-radio';
  }

  protected function renderInput() {
    $rows = array();
    foreach ($this->buttons as $button) {
      $id = celerity_generate_unique_node_id();
      $radio = phutil_render_tag(
        'input',
        array(
          'id' => $id,
          'type' => 'radio',
          'name' => $this->getName(),
          'value' => $button['value'],
          'checked' => ($button['value'] == $this->getValue())
            ? 'checked'
            : null,
          'disabled' => $this->getDisabled() ? 'disabled' : null,
        ));
      $label = phutil_render_tag(
        'label',
        array(
          'for' => $id,
        ),
        phutil_escape_html($button['label']));

      if (strlen($button['caption'])) {
        $label .=
          '<div class="aphront-form-radio-caption">'.
            phutil_escape_html($button['caption']).
          '</div>';
      }
      $rows[] =
        '<tr>'.
          '<td>'.$radio.'</td>'.
          '<th>'.$label.'</th>'.
        '</tr>';
    }

    return
      '<table class="aphront-form-control-radio-layout">'.
        implode("\n", $rows).
      '</table>';
  }

}
