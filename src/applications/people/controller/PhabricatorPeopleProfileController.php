<?php

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

  private function getMainFilters($username) {
    return array(
      array(
        'key' => 'feed',
        'name' => pht('Feed'),
        'href' => '/p/'.$username.'/feed/'
      ),
      array(
        'key' => 'about',
        'name' => pht('About'),
        'href' => '/p/'.$username.'/about/'
      )
    );
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

    $menu = new PhabricatorMenuView();
    foreach ($this->getMainFilters($username) as $filter) {
      $menu->newLink($filter['name'], $filter['href'], $filter['key']);
    }

    $menu->newLabel(pht('Activity'), 'activity');
    // NOTE: applications install the various links through PhabricatorEvent
    // listeners

    $oauths = id(new PhabricatorUserOAuthInfo())->loadAllWhere(
      'userID = %d',
      $user->getID());
    $oauths = mpull($oauths, null, 'getOAuthProvider');

    $providers = PhabricatorOAuthProvider::getAllProviders();
    $added_label = false;
    foreach ($providers as $provider) {
      if (!$provider->isProviderEnabled()) {
        continue;
      }

      $provider_key = $provider->getProviderKey();

      if (!isset($oauths[$provider_key])) {
        continue;
      }

      $name = pht('%s Profile', $provider->getProviderName());
      $href = $oauths[$provider_key]->getAccountURI();

      if ($href) {
        if (!$added_label) {
          $menu->newLabel(pht('Linked Accounts'), 'linked_accounts');
          $added_label = true;
        }
        $menu->addMenuItem(
          id(new PhabricatorMenuItemView())
          ->setIsExternal(true)
          ->setName($name)
          ->setHref($href)
          ->setType(PhabricatorMenuItemView::TYPE_LINK));
      }
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_PEOPLE_DIDRENDERMENU,
      array(
        'menu' => $menu,
        'person' => $user,
      ));
    $event->setUser($viewer);
    PhutilEventEngine::dispatchEvent($event);
    $nav = AphrontSideNavFilterView::newFromMenu($event->getValue('menu'));

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
        $header->setStatus(reset($statuses)->getTerseSummary($viewer));
      }
    }

    $nav->appendChild($header);

    $content = hsprintf('<div style="padding: 1em;">%s</div>', $content);
    $header->appendChild($content);

    if ($user->getPHID() == $viewer->getPHID()) {
      $nav->addFilter(
        null,
        pht('Edit Profile...'),
        '/settings/panel/profile/');
    }

    if ($viewer->getIsAdmin()) {
      $nav->addFilter(
        null,
        pht('Administrate User...'),
        '/people/edit/'.$user->getID().'/');
    }

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $user->getUsername(),
      ));
  }

  private function renderBasicInformation($user, $profile) {

    $blurb = nonempty(
      $profile->getBlurb(),
      '//'.pht('Nothing is known about this rare specimen.').'//');

    $engine = PhabricatorMarkupEngine::newProfileMarkupEngine();
    $blurb = $engine->markupText($blurb);

    $viewer = $this->getRequest()->getUser();

    $content = hsprintf(
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Basic Information</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>PHID</th>
              <td>%s</td>
            </tr>
            <tr>
              <th>User Since</th>
              <td>%s</td>
            </tr>
          </table>
        </div>
      </div>'.
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Flavor Text</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>Blurb</th>
              <td>%s</td>
            </tr>
          </table>
        </div>
      </div>',
      $user->getPHID(),
      phabricator_datetime($user->getDateCreated(), $viewer),
      $blurb);

    return $content;
  }

  private function renderUserFeed(PhabricatorUser $user) {
    $viewer = $this->getRequest()->getUser();

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $user->getPHID(),
      ));
    $query->setLimit(100);
    $query->setViewer($viewer);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($viewer);
    $view = $builder->buildView();

    return hsprintf(
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Activity Feed</h1>
        <div class="phabricator-profile-info-pane">%s</div>
      </div>',
      $view->render());
  }
}
