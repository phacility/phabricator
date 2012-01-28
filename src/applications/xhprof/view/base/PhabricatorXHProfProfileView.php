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

abstract class PhabricatorXHProfProfileView extends AphrontView {

  private $baseURI;
  private $isFramed;

  public function setIsFramed($is_framed) {
    $this->isFramed = $is_framed;
    return $this;
  }

  public function setBaseURI($uri) {
    $this->baseURI = $uri;
    return $this;
  }

  protected function renderSymbolLink($symbol) {
    return phutil_render_tag(
      'a',
      array(
        'href'    => $this->baseURI.'?symbol='.$symbol,
        'target'  => $this->isFramed ? '_top' : null,
      ),
      phutil_escape_html($symbol));
  }

}
