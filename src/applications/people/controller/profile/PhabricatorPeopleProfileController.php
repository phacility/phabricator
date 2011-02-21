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

class PhabricatorPeopleProfileController extends PhabricatorPeopleController {

  private $username;

  public function willProcessRequest(array $data) {
    $this->username = $data['username'];
  }

  public function processRequest() {

    $viewer = $this->getRequest()->getUser();

    $user = id(new PhabricatorUser())->loadOneWhere(
      'userName = %s',
      $this->username);
    if (!$user) {
      return new Aphront404Response();
    }

    $profile = id(new PhabricatorUserProfile())->loadOneWhere(
      'userPHID = %s',
      $user->getPHID());
    if (!$profile) {
      $profile = new PhabricatorUserProfile();
    }

    $links = array();

    if ($user->getPHID() == $viewer->getPHID()) {
      $links[] = phutil_render_tag(
        'a',
        array(
          'href' => '/profile/edit/',
        ),
        'Edit Profile');
    }

    $fbuid = $user->getFacebookUID();
    if ($fbuid) {
      $links[] = phutil_render_tag(
        'a',
        array(
          'href' => 'http://www.facebook.com/profile.php?id='.$fbuid,
        ),
        'Facebook Profile');
    }

    foreach ($links as $k => $link) {
      $links[$k] = '<li>'.$link.'</li>';
    }
    $links =
      '<ul class="profile-nav-links">'.
        implode("\n", $links).
      '</ul>';

    $title = nonempty($profile->getTitle(), 'Untitled Document');

    $username_tag =
      '<h1 class="profile-username">'.
        phutil_escape_html($user->getUserName()).
      '</h1>';
    $realname_tag =
      '<h2 class="profile-realname">'.
        '('.phutil_escape_html($user->getRealName()).')'.
      '</h2>';
    $title_tag =
      '<h2 class="profile-usertitle">'.
        phutil_escape_html($title).
      '</h2>';

    $src_phid = $profile->getProfileImagePHID();
    if (!$src_phid) {
      $src_phid = $user->getProfileImagePHID();
    }
    $src = PhabricatorFileURI::getViewURIForPHID($src_phid);

    $picture = phutil_render_tag(
      'img',
      array(
        'class' => 'profile-image',
        'src'   => $src,
      ));

    require_celerity_resource('phabricator-profile-css');

    $blurb = nonempty(
      $profile->getBlurb(),
      '//Nothing is known about this rare specimen.//');

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();
    $blurb = $engine->markupText($blurb);

    $content =
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Basic Information</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>PHID</th>
              <td>'.phutil_escape_html($user->getPHID()).'</td>
            </tr>
            <tr>
              <th>User Since</th>
              <td>'.phabricator_format_timestamp($user->getDateCreated()).'</td>
            </tr>
          </table>
        </div>
      </div>';
    $content .=
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Flavor Text</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>Blurb</th>
              <td>'.$blurb.'</td>
            </tr>
          </table>
        </div>
      </div>';

    $profile =
      '<table class="phabricator-profile-master-layout">
        <tr>
          <td class="phabricator-profile-navigation">'.
            $username_tag.
            $realname_tag.
            $title_tag.
            '<hr />'.
            $picture.
            '<hr />'.
            $links.
            '<hr />'.
          '</td>
          <td class="phabricator-profile-content">'.
          $content.
          '</td>
        </tr>
      </table>';

    return $this->buildStandardPageResponse(
      $profile,
      array(
        'title' => $user->getUsername(),
      ));
  }

}
