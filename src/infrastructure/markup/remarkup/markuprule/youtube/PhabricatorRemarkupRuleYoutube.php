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
 * @group markup
 */
final class PhabricatorRemarkupRuleYoutube
  extends PhutilRemarkupRule {

  public function apply($text) {
    $this->uri = new PhutilURI($text);

    if ($this->uri->getDomain() &&
        preg_match('/youtube\.com$/', $this->uri->getDomain())) {
      return $this->markupYoutubeLink();
    }

    return $text;
  }

  public function markupYoutubeLink() {
    $v = idx($this->uri->getQueryParams(), 'v');
    if ($v) {
      $youtube_src = 'https://www.youtube.com/embed/'.$v;
      $iframe =
        '<div class="embedded-youtube-video">'.
          phutil_render_tag(
            'iframe',
            array(
              'width'       => '650',
              'height'      => '400',
              'style'       => 'margin: 1em auto; border: 0px;',
              'src'         => $youtube_src,
              'frameborder' => 0,
            ),
            '').
        '</div>';
      return $this->getEngine()->storeText($iframe);
    } else {
      return $this->uri;
    }
  }

}
