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

final class PhabricatorProfileView extends AphrontView {

  protected $items = array();
  protected $profilePicture;
  protected $profileName;
  protected $profileRealname;
  protected $profileTitle;

  public function addProfileItem($item) {
    $this->items[] = $item;
    return $this;
  }

  public function setProfilePicture($picture) {
    $this->profilePicture = $picture;
    return $this;
  }

  public function setProfileNames($name, $realname = null, $title = null) {
    $this->profileName = $name;
    $this->profileRealname = $realname;
    $this->profileTitle = $title;
    return $this;
  }

  public function render() {
    $view = new AphrontNullView();
    $view->appendChild($this->items);

    $side_links = null;
    $realname = null;
    $title = null;
    if (!empty($this->profileRealname)) {
      $realname =
      '<h2 class="phabricator-profile-realname">'.
        phutil_escape_html($this->profileRealname).
      '</h2>';
    }

    if (!empty($this->profileTitle)) {
      $title =
      '<h2>'.
        phutil_escape_html($this->profileTitle).
      '</h2>';
    }

    if (!empty($this->items)) {
     $side_links =
       $view->render().
       '<hr />';
    }

    require_celerity_resource('phabricator-profile-css');

    return
      '<table class="phabricator-profile-master-layout">'.
        '<tr>'.
          '<td class="phabricator-profile-navigation">'.
            '<h1>'.phutil_escape_html($this->profileName).'</h1>'.
            $realname.
            $title.
            '<hr />'.
              '<img class="phabricator-profile-image" src="'.
                $this->profilePicture.
              '"/>'.
            '<hr />'.
            $side_links.
          '</td>'.
          '<td class="phabricator-profile-content">'.
            $this->renderChildren().
          '</td>'.
        '</tr>'.
      '</table>';
  }
}
