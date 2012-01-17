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

    require_celerity_resource('phabricator-profile-css');

    $profile = id(new PhabricatorUserProfile())->loadOneWhere(
      'userPHID = %s',
      $user->getPHID());
    if (!$profile) {
      $profile = new PhabricatorUserProfile();
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/p/'.$user->getUserName().'/'));
    $nav->addFilter('feed', 'Feed');
    $nav->addFilter('about', 'About');

    $nav->addSpacer();
    $nav->addLabel('Activity');

    $external_arrow = "\xE2\x86\x97";
    $nav->addFilter(
      null,
      "Revisions {$external_arrow}",
      '/differential/filter/revisions/?phid='.$user->getPHID());

    $nav->addFilter(
      null,
      "Tasks {$external_arrow}",
      '/maniphest/view/action/?users='.$user->getPHID());

    $nav->addFilter(
      null,
      "Commits {$external_arrow}",
      '/diffusion/author/'.$user->getUserName().'/');

    $oauths = id(new PhabricatorUserOAuthInfo())->loadAllWhere(
      'userID = %d',
      $user->getID());
    $oauths = mpull($oauths, null, 'getOAuthProvider');

    $providers = PhabricatorOAuthProvider::getAllProviders();
    $added_spacer = false;
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
        if (!$added_spacer) {
          $nav->addSpacer();
          $nav->addLabel('Linked Accounts');
          $added_spacer = true;
        }
        $nav->addFilter(null, $name.' '.$external_arrow, $href);
      }
    }

    $this->page = $nav->selectFilter($this->page, 'feed');

    switch ($this->page) {
      case 'feed':
        $content = $this->renderUserFeed($user);
        break;
      case 'about':
        $content = $this->renderBasicInformation($user, $profile);
        break;
      default:
        throw new Exception("Unknown page '{$this->page}'!");
    }

    $src_phid = $user->getProfileImagePHID();
    $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $src_phid);
    if ($file) {
      $picture = $file->getBestURI();
    } else {
      $picture = null;
    }

    $header = new PhabricatorProfileHeaderView();
    $header
      ->setProfilePicture($picture)
      ->setName($user->getUserName().' ('.$user->getRealName().')')
      ->setDescription($profile->getTitle());

    $header->appendChild($nav);
    $nav->appendChild(
      '<div style="padding: 1em;">'.$content.'</div>');

    if ($user->getPHID() == $viewer->getPHID()) {
      $nav->addSpacer();
      $nav->addFilter(null, 'Edit Profile...', '/settings/page/profile/');
    }

    if ($viewer->getIsAdmin()) {
      $nav->addSpacer();
      $nav->addFilter(
        null,
        'Administrate User...',
        '/people/edit/'.$user->getID().'/');
    }

    return $this->buildStandardPageResponse(
      $header,
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
    $commit_list =
      phutil_render_tag(
        'a',
        array(
          'href' => '/diffusion/author/'.
            phutil_escape_uri($user->getUsername()),
        ),
        'Recent Commits');

    $viewer = $this->getRequest()->getUser();

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
              <td>'.phabricator_datetime($user->getDateCreated(),
                                         $viewer).
             '</td>
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

  private function renderUserFeed(PhabricatorUser $user) {
    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $user->getPHID(),
      ));
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($this->getRequest()->getUser());
    $view = $builder->buildView();

    return
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Activity Feed</h1>
        <div class="phabricator-profile-info-pane">
          '.$view->render().'
        </div>
      </div>';
  }
}
