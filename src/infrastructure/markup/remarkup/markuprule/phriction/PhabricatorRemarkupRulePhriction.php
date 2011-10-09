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
class PhabricatorRemarkupRulePhriction
  extends PhutilRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      '@\B\\[\\[([^|\\]]+)(?:\\|([^\\]]+))?\\]\\]\B@U',
      array($this, 'markupDocumentLink'),
      $text);
  }

  public function markupDocumentLink($matches) {

    $slug = trim($matches[1]);
    $name = trim(idx($matches, 2, $slug));

    // If whatever is being linked to begins with "/" or has "://", treat it
    // as a URI instead of a wiki page.
    $is_uri = preg_match('@(^/)|(://)@', $slug);

    if ($is_uri) {
      $protocols = $this->getEngine()->getConfig(
        'uri.allowed-protocols',
        array());
      $protocol = id(new PhutilURI($slug))->getProtocol();
      if (!idx($protocols, $protocol)) {
        // Don't treat this as a URI if it's not an allowed protocol.
        $is_uri = false;
      }
    }

    if ($is_uri) {
      $uri = $slug;
      // Leave the name unchanged, i.e. link the whole URI if there's no
      // explicit name.
    } else {
      $name = explode('/', trim($name, '/'));
      $name = end($name);

      $slug = PhrictionDocument::normalizeSlug($slug);
      $uri  = PhrictionDocument::getSlugURI($slug);
    }

    return $this->getEngine()->storeText(
      phutil_render_tag(
        'a',
        array(
          'href'  => $uri,
          'class' => $is_uri ? null : 'phriction-link',
        ),
        phutil_escape_html($name)));
  }

}
