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

class PhabricatorDirectoryMainController
  extends PhabricatorDirectoryController {

  public function processRequest() {
    $items = id(new PhabricatorDirectoryItem())->loadAll();
    $items = msort($items, 'getSortKey');

    $categories = id(new PhabricatorDirectoryCategory())->loadAll();
    $categories = msort($categories, 'getSequence');

    $category_map = mpull($categories, 'getName', 'getID');
    $category_map[0] = 'Free Radicals';
    $items = mgroup($items, 'getCategoryID');

    $content = array();
    foreach ($category_map as $id => $category_name) {
      $category_items = idx($items, $id);
      if (!$category_items) {
        continue;
      }

      $item_markup = array();
      foreach ($category_items as $item) {
        $item_markup[] =
          '<div>'.
            '<h2>'.
              phutil_render_tag(
                'a',
                array(
                  'href' => $item->getHref(),
                ),
                phutil_escape_html($item->getName())).
            '</h2>'.
            '<p>'.phutil_escape_html($item->getDescription()).'</p>'.
          '</div>';
      }

      $content[] =
        '<div class="aphront-directory-category">'.
          '<h1>'.phutil_escape_html($category_name).'</h1>'.
          '<div class="aphront-directory-group">'.
            implode("\n", $item_markup).
          '</div>'.
        '</div>';
    }

    $content =
      '<div class="aphront-directory-list">'.
        implode("\n", $content).
      '</div>';

    return $this->buildStandardPageResponse($content, array(
      'title' => 'Directory',
      'tab'   => 'directory',
    ));
  }

}
