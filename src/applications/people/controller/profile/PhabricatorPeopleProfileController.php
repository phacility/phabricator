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
  private $page;

  public function willProcessRequest(array $data) {
    $this->username = idx($data, 'username');
    $this->page = idx($data, 'page');
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

    $oauths = id(new PhabricatorUserOAuthInfo())->loadAllWhere(
      'userID = %d',
      $user->getID());
    $oauths = mpull($oauths, null, 'getOAuthProvider');

    $providers = PhabricatorOAuthProvider::getAllProviders();
    foreach ($providers as $provider) {
      if (!$provider->isProviderEnabled()) {
        continue;
      }

      $provider_key = $provider->getProviderKey();

      if (!isset($oauths[$provider_key])) {
        continue;
      }

      $name = $provider->getProviderName().' Profile';
      $href = $oauths[$provider_key]->getAccountURI();

      if ($href) {
        $links[] = phutil_render_tag(
          'a',
          array(
            'href' => $href,
          ),
          phutil_escape_html($name));
      }
    }

    // TODO:  perhaps, if someone wants to add to the profile of the user the
    //        ability to show the task/revisions where he is working/commenting
    //        on, this has to be changed to something like
    //        |$this->page = key($pages)|, since the "page" regexp was added to
    //        the aphrontconfiguration.
    if (empty($links[$this->page])) {
      $this->page = 'action';
    }

    switch ($this->page) {
      default:
        $content = $this->renderBasicInformation($user, $profile);
        break;
    }

    $src_phid = $profile->getProfileImagePHID();
    if (!$src_phid) {
      $src_phid = $user->getProfileImagePHID();
    }
    $picture = PhabricatorFileURI::getViewURIForPHID($src_phid);
    $title = nonempty($profile->getTitle(), 'Untitled Document');
    $realname = '('.$user->getRealName().')';

    $profile = new PhabricatorProfileView();
    $profile->setProfilePicture($picture);
    $profile->setProfileNames(
      $user->getUserName(),
      $realname,
      $title);
    foreach ($links as $page => $name) {
      if (is_integer($page)) {
        $profile->addProfileItem(
          phutil_render_tag(
            'span',
            array(),
            $name));
      } else {
        $profile->addProfileItem($page);
      }
    }

    $profile->appendChild($content);
    return $this->buildStandardPageResponse(
      $profile,
      array(
        'title' => $user->getUsername(),
      ));
  }

  private function renderBasicInformation($user, $profile) {
    $blurb = nonempty(
      $profile->getBlurb(),
      '//Nothing is known about this rare specimen.//');

    $engine = PhabricatorMarkupEngine::newProfileMarkupEngine();
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

    return $content;
  }
}
