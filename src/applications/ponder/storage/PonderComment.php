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

final class PonderComment extends PonderDAO
  implements PhabricatorMarkupInterface {

  const MARKUP_FIELD_CONTENT = 'markup:content';

  protected $targetPHID;
  protected $authorPHID;
  protected $content;

  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    $id = $this->getID();
    return "ponder:c{$id}:{$field}:{$hash}";
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newPonderMarkupEngine();
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

  public function getMarkupField() {
    return self::MARKUP_FIELD_CONTENT;
  }
}

