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

  const RANDOM_IMAGE_NAME = 'randomon';
  private $images = array();
  private $hash = 0;

  public function __construct() {
    $rows = id(new PhabricatorFileImageMacro())->loadAll();
    foreach ($rows as $row) {
      $this->images[$row->getName()] = $row->getFilePHID();
    }
    $this->images[self::RANDOM_IMAGE_NAME] = '';
    $this->hash = 0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@\b([a-zA-Z0-9_\-]+)\b@',
      array($this, 'markupImageMacro'),
      $text);
  }

  /**
   * Silly function for generating some 'randomness' based on the
   * words in the text
   */
  private function updateHash($word) {
    // Simple Jenkins hash
    for ($ii = 0; $ii < strlen($word); $ii++) {
      $this->hash += ord($word[$ii]);
      $this->hash += ($this->hash << 10);
      $this->hash ^= ($this->hash >> 6);
    }
  }

  public function markupImageMacro($matches) {
    // Update the hash that is used for defining each 'randomon' image. This way
    // each 'randomon' image will be different, but they won't change when the
    // text is updated.
    $this->updateHash($matches[1]);

    if (array_key_exists($matches[1], $this->images)) {
      if ($matches[1] === self::RANDOM_IMAGE_NAME) {
        $keys = array_keys($this->images);
        $phid = $this->images[$keys[$this->hash % count($this->images)]];
      } else {
        $phid = $this->images[$matches[1]];
      }

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
