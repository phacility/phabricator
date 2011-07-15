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

/**
 * @group markup
 */
class PhabricatorRemarkupRuleEmbedFile
  extends PhutilRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      "@{F(\d+)}@",
      array($this, 'markupEmbedFile'),
      $text);
  }

  public function markupEmbedFile($matches) {

    $file = null;
    if ($matches[1]) {
      // TODO: This is pretty inefficient if there are a bunch of files.
      $file = id(new PhabricatorFile())->load($matches[1]);
    }

    if ($file) {
      return $this->getEngine()->storeText(
        phutil_render_tag(
          'a',
          array(
            'href' => $file->getViewURI(),
            'target' => '_blank',
          ),
          phutil_render_tag(
            'img',
            array(
              'src' => $file->getThumb160x120URI(),
            ))));
    } else {
      return $matches[0];
    }
  }

}
