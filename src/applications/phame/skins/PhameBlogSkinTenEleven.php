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
final class PhameBlogSkinTenEleven extends PhameBasicBlogSkin {

  public function getName() {
    return 'Ten Eleven (Default)';
  }

  protected function renderHeader() {
    $blog_name = $this->getBlog()->getName();
    $about = $this->getBlog()->getDescription();

    $about = phutil_escape_html($about);

    return <<<EOHTML
<div class="main-panel">
  <div class="header">
    <h1>{$blog_name}</h1>
    <div class="about">{$about}</div>
  </div>
  <div class="splash">

  </div>
  <div class="content">
EOHTML;
  }

  protected function renderFooter() {
    return <<<EOHTML
  </div>
</div>
EOHTML;
  }

}
