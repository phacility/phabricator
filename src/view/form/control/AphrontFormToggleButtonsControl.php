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

final class AphrontFormToggleButtonsControl extends AphrontFormControl {

  private $baseURI;
  private $param;

  private $buttons;

  public function setBaseURI(PhutilURI $uri, $param) {
    $this->baseURI = $uri;
    $this->param = $param;
    return $this;
  }

  public function setButtons(array $buttons) {
    $this->buttons = $buttons;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-togglebuttons';
  }

  protected function renderInput() {
    if (!$this->baseURI) {
      throw new Exception('Call setBaseURI() before render()!');
    }

    $selected = $this->getValue();

    $out = array();
    foreach ($this->buttons as $value => $label) {
      if ($value == $selected) {
        $more = ' toggle-selected toggle-fixed';
      } else {
        $more = null;
      }

      $out[] = phutil_render_tag(
        'a',
        array(
          'class' => 'toggle'.$more,
          'href'  => $this->baseURI->alter($this->param, $value),
        ),
        phutil_escape_html($label));
    }

    return implode('', $out);
  }

}
