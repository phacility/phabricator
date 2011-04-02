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

final class AphrontRequestFailureView extends AphrontView {

  private $header;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }


  final public function render() {
    require_celerity_resource('aphront-request-failure-view-css');

    return
      '<div class="aphront-request-failure-view">'.
        '<div class="aphront-request-failure-head">'.
          '<h1>'.phutil_escape_html($this->header).'</h1>'.
        '</div>'.
        '<div class="aphront-request-failure-body">'.
          $this->renderChildren().
        '</div>'.
      '</div>';
  }

}
