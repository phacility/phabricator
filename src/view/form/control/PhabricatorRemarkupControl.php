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

final class PhabricatorRemarkupControl extends AphrontFormTextAreaControl {

  public function getCaption() {

    $caption = parent::getCaption();
    if ($caption) {
      $caption_suffix = '<br />'.$caption;
    } else {
      $caption_suffix = '';
    }

    return phutil_render_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'article/Remarkup_Reference.html'),
        'tabindex' => '-1',
        'target' => '_blank',
      ),
      'Formatting Reference') .
      $caption_suffix;
  }

}
