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

final class PhabricatorFileSideNavView extends AphrontView {
  private $selectedFilter;

  public function setSelectedFilter($selected_filter) {
    $this->selectedFilter = $selected_filter;
    return $this;
  }
  private function getSelectedFilter() {
    return $this->selectedFilter;
  }

  public function render() {
    $selected_filter = $this->getSelectedFilter();
    if (!$selected_filter) {
      throw new Exception("Call setFilter() before render()!");
    }

    $filters = array(
      'Files' => array(),
      'upload' => array(
        'name' => 'Upload File',
        'href' => '/file/filter/upload/'
      ),
      'my' => array(
        'name' => 'My Files',
        'href' => '/file/filter/my/'
      ),
      'all' => array(
        'name' => 'All Files',
        'href' => '/file/filter/all/'
      ),
      // TODO: Remove this fairly soon.
      '<br />' => null,
      '<div style="font-weight: normal; font-size: smaller; '.
      'white-space: normal;">NOTE: Macros have moved to a separate '.
      'application. Use the "Search" field to jump to it or choose '.
      'More Stuff &raquo; Macros from the home page.</span>' => null,
    );

    $side_nav = new AphrontSideNavView();
    foreach ($filters as $filter_key => $filter) {
      // more of a label than a filter
      if (empty($filter)) {
        $side_nav->addNavItem(phutil_render_tag(
          'span',
          array(),
          $filter_key));
        continue;
      }
      $selected = $filter_key == $selected_filter;
      $side_nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => $filter['href'],
            'class' => $selected ? 'aphront-side-nav-selected': null,
          ),
          $filter['name'])
        );
    }
    $side_nav->appendChild($this->renderChildren());

    return $side_nav->render();
  }
}
