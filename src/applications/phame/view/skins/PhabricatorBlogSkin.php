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

/**
 * @group phame
 */
final class PhabricatorBlogSkin extends PhameBlogSkin {

  public function __construct() {
    $this->setShowChrome(true);
  }

  protected function renderHeader() {
    return '';
  }

  protected function renderFooter() {
    return '';
  }

  protected function renderBody() {
    require_celerity_resource('phame-css');

    return
      $this->renderNotice() .
      $this->renderPosts() .
      $this->renderBlogDetails();
  }
}
