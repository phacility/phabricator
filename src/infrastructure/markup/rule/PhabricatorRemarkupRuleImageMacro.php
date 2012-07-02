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
final class PhabricatorRemarkupRuleImageMacro
  extends PhutilRemarkupRule {

  private $images = array();

  public function __construct() {
    $rows = id(new PhabricatorFileImageMacro())->loadAll();
    foreach ($rows as $row) {
      $this->images[$row->getName()] = $row->getFilePHID();
    }
  }

  public function apply($text) {
    return preg_replace_callback(
      '@^([a-zA-Z0-9_\-]+)$@m',
      array($this, 'markupImageMacro'),
      $text);
  }

  public function markupImageMacro($matches) {
    if (array_key_exists($matches[1], $this->images)) {
      $phid = $this->images[$matches[1]];

      $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $phid);
      if ($file) {
        $src_uri = $file->getBestURI();
      } else {
        $src_uri = null;
      }

      $img = phutil_render_tag(
        'img',
        array(
          'src'   => $src_uri,
          'alt'   => $matches[1],
          'title' => $matches[1]),
        null);
      return $this->getEngine()->storeText($img);
    } else {
      return $matches[1];
    }
  }

}
