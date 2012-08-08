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
final class PhabricatorRemarkupRuleProxyImage
  extends PhutilRemarkupRule {

  public function apply($text) {

    $filetypes = '\.(?:jpe?g|png|gif)';

    $text = preg_replace_callback(
      '@[<](\w{3,}://.+?'.$filetypes.')[>]@',
      array($this, 'markupProxyImage'),
      $text);

    $text = preg_replace_callback(
      '@(?<=^|\s)(\w{3,}://\S+'.$filetypes.')(?=\s|$)@',
      array($this, 'markupProxyImage'),
      $text);

    return $text;
  }

  public function markupProxyImage($matches) {

    $uri = PhabricatorFileProxyImage::getProxyImageURI($matches[1]);

    return $this->getEngine()->storeText(
      phutil_render_tag(
        'a',
        array(
          'href' => $uri,
          'target' => '_blank',
        ),
        phutil_render_tag(
          'img',
          array(
            'src' => $uri,
            'class' => 'remarkup-proxy-image',
          ))));
  }

}
