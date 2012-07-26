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

final class AphrontFormImageControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-image';
  }

  protected function renderInput() {
    $id = celerity_generate_unique_node_id();

    return
      phutil_render_tag(
        'input',
        array(
          'type'  => 'file',
          'name'  => $this->getName(),
          'class' => 'image',
        )).
      '<div style="clear: both;">'.
      phutil_render_tag(
        'input',
        array(
          'type'  => 'checkbox',
          'name'  => 'default_image',
          'class' => 'default-image',
          'id'    => $id,
        )).
      phutil_render_tag(
        'label',
        array(
          'for' => $id,
        ),
        'Use Default Image instead').
      '</div>';
  }

}
