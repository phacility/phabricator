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

final class PhabricatorDirectoryCategoryViewController
  extends PhabricatorDirectoryController {

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function shouldRequireAdmin() {
    return false;
  }

  public function processRequest() {
    $category = id(new PhabricatorDirectoryCategory())->load($this->id);
    if (!$category) {
      return new Aphront404Response();
    }

    $items = id(new PhabricatorDirectoryItem())->loadAllWhere(
      'categoryID = %d',
      $category->getID());
    $items = msort($items, 'getSortKey');

    $nav = $this->buildNav();
    $nav->selectFilter('directory/'.$this->id, 'directory/'.$this->id);

    require_celerity_resource('phabricator-directory-css');

    $item_markup = array();
    foreach ($items as $item) {
      $item_markup[] =
        '<div class="aphront-directory-item">'.
          '<h1>'.
            phutil_render_tag(
              'a',
              array(
                'href' => $item->getHref(),
              ),
              phutil_escape_html($item->getName())).
          '</h1>'.
          '<p>'.phutil_escape_html($item->getDescription()).'</p>'.
        '</div>';
    }

    $content =
      '<div class="aphront-directory-list">'.
        implode("\n", $item_markup).
      '</div>';


    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Directory Category List',
        'tab'   => 'categories',
      ));
  }

}
