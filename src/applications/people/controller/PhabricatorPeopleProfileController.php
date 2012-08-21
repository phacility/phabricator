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

final class PhabricatorPeopleProfileController
  extends PhabricatorPeopleController {

  private $username;
  private $page;
  private $profileUser;

  public function willProcessRequest(array $data) {
    $this->username = idx($data, 'username');
    $this->page = idx($data, 'page');
  }

  public function getProfileUser() {
    return $this->profileUser;
  }

  public function processRequest() {

    $viewer = $this->getRequest()->getUser();

    $user = id(new PhabricatorUser())->loadOneWhere(
      'userName = %s',
      $this->username);
    if (!$user) {
      return new Aphront404Response();
    }

    $this->profileUser = $user;

    require_celerity_resource('phabricator-profile-css');

    $profile = id(new PhabricatorUserProfile())->loadOneWhere(
      'userPHID = %s',
      $user->getPHID());
    if (!$profile) {
      $profile = new PhabricatorUserProfile();
    }
    $username = phutil_escape_uri($user->getUserName());

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/p/'.$username.'/'));
    $nav->addFilter('feed', 'Feed');
    $nav->addFilter('about', 'About');

    $nav->addSpacer();
    $nav->addLabel('Activity');

    $external_arrow = "\xE2\x86\x97";
    $nav->addFilter(
      null,
      "Revisions {$external_arrow}",
      '/differential/filter/revisions/'.$username.'/');

    $nav->addFilter(
      null,
      "Tasks {$external_arrow}",
      '/maniphest/view/action/?users='.$user->getPHID());

    $nav->addFilter(
      null,
      "Commits {$external_arrow}",
      '/audit/view/author/'.$username.'/');

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

    $picture = $user->loadProfileImageURI();

    $header = new PhabricatorProfileHeaderView();
    $header
      ->setProfilePicture($picture)
      ->setName($user->getUserName().' ('.$user->getRealName().')')
      ->setDescription($profile->getTitle());

    if ($user->getIsDisabled()) {
      $header->setStatus('Disabled');
    } else {
      $statuses = id(new PhabricatorUserStatus())->loadCurrentStatuses(
        array($user->getPHID()));
      if ($statuses) {
        $header->setStatus(reset($statuses)->getStatusDescription($viewer));
      }
    }

    $header->appendChild($nav);
    $nav->appendChild(
      '<div style="padding: 1em;">'.$content.'</div>');

    if ($user->getPHID() == $viewer->getPHID()) {
      $nav->addSpacer();
      $nav->addFilter(null, 'Edit Profile...', '/settings/panel/profile/');
    }

    if ($viewer->getIsAdmin()) {
      $nav->addSpacer();
      $nav->addFilter(
        null,
        'Administrate User...',
        '/people/edit/'.$user->getID().'/');
    }

    return $this->buildApplicationPage(
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
    $content .= $this->renderActivityAnalysis($user);

    return $content;
  }

  private function renderActivityAnalysis(PhabricatorUser $user) {

    $stories = $this->getStories($user, 500);
    $counts = $this->countStories($stories);
    
    $behavior = $this->analyzeActivity($counts);
    switch ($behavior) {
    case self::DIURNAL:
      $behavior_str = "//This creature appears to be diurnal.//";
      break;
    case self::NOCTURNAL;
      $behavior_str = "//This creature appears to be nocturnal.//";
      break;
    case self::CATHEMERAL;
      $behavior_str = "//This creature appears to be " .
        "[[http://en.wikipedia.org/wiki/Cathemeral | cathemeral]].//";
      break;
    }
    $engine = PhabricatorMarkupEngine::newProfileMarkupEngine();
    $behavior_str = $engine->markupText($behavior_str);
    
    $id = celerity_generate_unique_node_id();
    $punchcard_node = phutil_render_tag(
      'div',
      array(
        'id' => $id,
        'style' => 'border: 1px solid #6f6f6f; '.
                   'margin: 1em 2em; '.
                   'height: 280px; '.
                   'width: 700px; ',
      ),
      '');

    require_celerity_resource('javelin-behavior-punchcard');

    Javelin::initBehavior('punchcard', array(
      'hardpoint' => $id,
      'counts' => $counts,
    ));

    return
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Sightings In The Wild</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>Behavior Pattern</th>
              <td>'.$behavior_str.'</td>
            </tr>
          </table>
        </div>
      </div>
      '.$punchcard_node;
  }

  // Tabulate stories by day of week and hour of day.
  private function countStories($stories) {

    $counts = array_fill(1, 7, array_fill(0, 24, 0));
    foreach ($stories as $story) {
      $epoch = $story->getEpoch();
      ++$counts[intval(date('N', $epoch))][intval(date('G', $epoch))];
    }
    return $counts;
  }

  const DIURNAL = 1;
  const NOCTURNAL = 2;
  const CATHEMERAL = 3;

  private function analyzeActivity($counts) {
    $day_count = 0;
    $night_count = 0;
    foreach ($counts as $day) {
      foreach ($day as $hour => $count) {
        if (($hour >= 8) && ($hour < 20)) {
          $day_count += $count;
        } else {
          $night_count += $count;
        }
      }
    }
    
    if ($day_count / ($day_count + $night_count) > 0.66) {
      return self::DIURNAL;
    } else if ($day_count / ($day_count + $night_count) < 0.66) {
      return self::NOCTURNAL;
    } else {
      return self::CATHEMERAL;
    }
  }

  private function renderUserFeed(PhabricatorUser $user) {

    $viewer = $this->getRequest()->getUser();
    $stories = $this->getStories($user, 100);
    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($viewer);
    $view = $builder->buildView();

    return
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Activity Feed</h1>
        <div class="phabricator-profile-info-pane">
          '.$view->render().'
        </div>
      </div>';
  }

  private function getStories(PhabricatorUser $user, $limit) {
    $viewer = $this->getRequest()->getUser();

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $user->getPHID(),
      ));
    $query->setLimit($limit);
    $query->setViewer($viewer);
    return $query->execute();
  }
}
