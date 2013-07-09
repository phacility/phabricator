<?php

final class PhabricatorPeopleProfileController
  extends PhabricatorPeopleController {

  private $username;
  private $page;
  private $profileUser;

  public function shouldRequireAdmin() {
    // Default for people app is true
    // We desire public access here
    return false;
  }

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

    $profile = $user->loadUserProfile();
    $username = phutil_escape_uri($user->getUserName());

    $menu = new PHUIListView();
    foreach ($this->getMainFilters($username) as $filter) {
      $menu->newLink($filter['name'], $filter['href'], $filter['key']);
    }

    $menu->newLabel(pht('Activity'), 'activity');
    // NOTE: applications install the various links through PhabricatorEvent
    // listeners

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

    $header = id(new PhabricatorHeaderView())
      ->setHeader($user->getUserName().' ('.$user->getRealName().')')
      ->setSubheader($profile->getTitle())
      ->setImage($picture);

    if ($user->getIsDisabled()) {
      $header->setStatus(pht('Disabled'));
    } else {
      $statuses = id(new PhabricatorUserStatus())->loadCurrentStatuses(
        array($user->getPHID()));
      if ($statuses) {
        $header->setStatus(reset($statuses)->getTerseSummary($viewer));
      }
    }

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $can_edit = ($user->getPHID() == $viewer->getPHID());

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Profile'))
        ->setHref($this->getApplicationURI('editprofile/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('image')
        ->setName(pht('Edit Profile Picture'))
        ->setHref($this->getApplicationURI('picture/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($viewer->getIsAdmin()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('blame')
          ->setName(pht('Administrate User'))
          ->setHref($this->getApplicationURI('edit/'.$user->getID().'/')));
    }

    $nav->appendChild($header);
    $nav->appendChild($actions);
    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $user->getUsername(),
        'device' => true,
        'dust' => true,
      ));
  }

  private function renderBasicInformation($user, $profile) {

    $blurb = nonempty(
      $profile->getBlurb(),
      '//'.pht('Nothing is known about this rare specimen.').'//');

    $viewer = $this->getRequest()->getUser();

    $engine = PhabricatorMarkupEngine::newProfileMarkupEngine();
    $engine->setConfig('viewer', $viewer);
    $blurb = $engine->markupText($blurb);

    $content = hsprintf(
      '<div class="phabricator-profile-info-group profile-wrap-responsive">
        <h1 class="phabricator-profile-info-header">%s</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>%s</th>
              <td>%s</td>
            </tr>
            <tr>
              <th>%s</th>
              <td>%s</td>
            </tr>
          </table>
        </div>
      </div>'.
      '<div class="phabricator-profile-info-group profile-wrap-responsive">
        <h1 class="phabricator-profile-info-header">%s</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>%s</th>
              <td>%s</td>
            </tr>
          </table>
        </div>
      </div>',
      pht('Basic Information'),
      pht('PHID'),
      $user->getPHID(),
      pht('User Since'),
      phabricator_datetime($user->getDateCreated(), $viewer),
      pht('Flavor Text'),
      pht('Blurb'),
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
      '<div class="profile-feed profile-wrap-responsive">
        %s
      </div>',
      $view->render());
  }
}
