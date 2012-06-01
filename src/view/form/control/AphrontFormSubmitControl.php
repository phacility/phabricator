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

final class AphrontFormSubmitControl extends AphrontFormControl {

  protected $cancelButton;

  public function addCancelButton($href, $label = 'Cancel') {
    $this->cancelButton = phutil_render_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'button grey',
      ),
      phutil_escape_html($label));
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-submit';
  }

  protected function renderInput() {
    $submit_button = null;
    if ($this->getValue()) {
      $submit_button = phutil_render_tag(
        'button',
        array(
          'name'      => '__submit__',
          'disabled'  => $this->getDisabled() ? 'disabled' : null,
        ),
        phutil_escape_html($this->getValue()));
    }
    return $submit_button.$this->cancelButton;
  }

}
