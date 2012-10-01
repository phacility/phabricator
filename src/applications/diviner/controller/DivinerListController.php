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

final class DivinerListController extends PhabricatorController {

  public function processRequest() {

    // TODO: Temporary implementation until Diviner is up and running inside
    // Phabricator.

    $links = array(
      'http://www.phabricator.com/docs/phabricator/' => array(
        'name'    => 'Phabricator Ducks',
        'flavor'  => 'Oops, that should say "Docs".',
      ),
      'http://www.phabricator.com/docs/arcanist/' => array(
        'name'    => 'Arcanist Docs',
        'flavor'  => 'Words have never been so finely crafted.',
      ),
      'http://www.phabricator.com/docs/libphutil/' => array(
        'name'    => 'libphutil Docs',
        'flavor'  => 'Soothing prose; seductive poetry.',
      ),
      'http://www.phabricator.com/docs/javelin/' => array(
        'name'    => 'Javelin Docs',
        'flavor'  => 'O, what noble scribe hath penned these words?',
      ),
    );

    require_celerity_resource('phabricator-directory-css');

    $out = array();
    foreach ($links as $href => $link) {
      $name = $link['name'];
      $flavor = $link['flavor'];

      $link = phutil_render_tag(
        'a',
        array(
          'href'    => $href,
          'target'  => '_blank',
        ),
        phutil_escape_html($name));

      $out[] =
        '<div class="aphront-directory-item">'.
          '<h1>'.$link.'</h1>'.
          '<p>'.phutil_escape_html($flavor).'</p>'.
        '</div>';
    }

    $out =
      '<div class="aphront-directory-list">'.
        implode("\n", $out).
      '</div>';

    return $this->buildApplicationPage(
      $out,
      array(
        'device' => true,
        'title' => 'Documentation',
      ));
  }
}
