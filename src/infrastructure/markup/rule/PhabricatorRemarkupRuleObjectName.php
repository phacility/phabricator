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
abstract class PhabricatorRemarkupRuleObjectName
  extends PhutilRemarkupRule {

  abstract protected function getObjectNamePrefix();

  public function apply($text) {
    $prefix = $this->getObjectNamePrefix();
    return preg_replace_callback(
      "@\b({$prefix})([1-9]\d*)(?:#([-\w\d]+))?\b@",
      array($this, 'markupObjectNameLink'),
      $text);
  }

  public function markupObjectNameLink($matches) {
    list(, $prefix, $id) = $matches;

    if (isset($matches[3])) {
      $href = $matches[3];
      $text = $matches[3];
      if (preg_match('@^(?:comment-)?(\d{1,7})$@', $href, $matches)) {
        // Maximum length is 7 because 12345678 could be a file hash.
        $href = "comment-{$matches[1]}";
        $text = $matches[1];
      }
      $href = "/{$prefix}{$id}#{$href}";
      $text = "{$prefix}{$id}#{$text}";
    } else {
      $href = "/{$prefix}{$id}";
      $text = "{$prefix}{$id}";
    }

    return $this->getEngine()->storeText(
      phutil_render_tag(
        'a',
        array(
          'href' => $href,
        ),
        $text));
  }

}
