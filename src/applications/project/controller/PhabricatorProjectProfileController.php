<?php

final class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  private $id;
  private $page;
  private $project;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->page = idx($data, 'page');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withIDs(array($this->id));

    if ($this->page == 'people') {
      $query->needMembers(true);
    }

    $project = $query->executeOne();
    $this->project = $project;
    if (!$project) {
      return new Aphront404Response();
    }

    $profile = $project->loadProfile();
    if (!$profile) {
      $profile = new PhabricatorProjectProfile();
    }

    $picture = $profile->loadProfileImageURI();

    $nav_view = $this->buildLocalNavigation($project);

    $this->page = $nav_view->selectFilter($this->page, 'dashboard');

    require_celerity_resource('phabricator-profile-css');
    switch ($this->page) {
      case 'dashboard':
        $content = $this->renderTasksPage($project, $profile);

        $query = new PhabricatorFeedQuery();
        $query->setFilterPHIDs(
          array(
            $project->getPHID(),
          ));
        $query->setLimit(50);
        $query->setViewer($this->getRequest()->getUser());
        $stories = $query->execute();

        $content = hsprintf('%s%s', $content, $this->renderStories($stories));
        break;
      case 'about':
        $content = $this->renderAboutPage($project, $profile);
        break;
      case 'people':
        $content = $this->renderPeoplePage($project, $profile);
        break;
      case 'feed':
        $content = $this->renderFeedPage($project, $profile);
        break;
      default:
        throw new Exception("Unimplemented filter '{$this->page}'.");
    }


    $header = new PhabricatorProfileHeaderView();
    $header->setName($project->getName());
    $header->setDescription(
      phutil_utf8_shorten($profile->getBlurb(), 1024));
    $header->setProfilePicture($picture);

    $action = null;
    if (!$project->isUserMember($user->getPHID())) {
      $can_join = PhabricatorPolicyCapability::CAN_JOIN;

      if (PhabricatorPolicyFilter::hasCapability($user, $project, $can_join)) {
        $class = 'green';
      } else {
        $class = 'grey disabled';
      }

      $action = phabricator_form(
        $user,
        array(
          'action' => '/project/update/'.$project->getID().'/join/',
          'method' => 'post',
        ),
        phutil_tag(
          'button',
          array(
            'class' => $class,
          ),
          pht('Join Project')));
    } else {
      $action = javelin_tag(
        'a',
        array(
          'href'  => '/project/update/'.$project->getID().'/leave/',
          'sigil' => 'workflow',
          'class' => 'grey button',
        ),
        pht('Leave Project...'));
    }

    $header->addAction($action);

    $nav_view->appendChild($header);

    $content = hsprintf('<div style="padding: 1em;">%s</div>', $content);
    $header->appendChild($content);

    return $this->buildApplicationPage(
      $nav_view,
      array(
        'title' => pht('%s Project', $project->getName()),
      ));
  }

  private function renderAboutPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $viewer = $this->getRequest()->getUser();

    $blurb = $profile->getBlurb();
    $blurb = phutil_escape_html_newlines($blurb);

    $phids = array($project->getAuthorPHID());
    $phids = array_unique($phids);
    $handles = $this->loadViewerHandles($phids);

    $timestamp = phabricator_datetime($project->getDateCreated(), $viewer);

    $about = hsprintf(
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">About</h1>
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
            <tr>
              <th>PHID</th>
              <td>%s</td>
            </tr>
            <tr>
              <th>%s</th>
              <td>%s</td>
            </tr>
          </table>
        </div>
      </div>',
      pht('Creator'),
      $handles[$project->getAuthorPHID()]->renderLink(),
      pht('Created'),
      $timestamp,
      $project->getPHID(),
      pht('Blurb'),
      $blurb);

    return $about;
  }

  private function renderPeoplePage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $member_phids = $project->getMemberPHIDs();
    $handles = $this->loadViewerHandles($member_phids);

    $affiliated = array();
    foreach ($handles as $phids => $handle) {
      $affiliated[] = phutil_tag('li', array(), $handle->renderLink());
    }

    if ($affiliated) {
      $affiliated = phutil_tag('ul', array(), $affiliated);
    } else {
      $affiliated = hsprintf('<p><em>%s</em></p>', pht(
        'No one is affiliated with this project.'));
    }

    return hsprintf(
      '<div class="phabricator-profile-info-group">'.
        '<h1 class="phabricator-profile-info-header">%s</h1>'.
        '<div class="phabricator-profile-info-pane">%s</div>'.
      '</div>',
      pht('People'),
      $affiliated);
  }

  private function renderFeedPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(array($project->getPHID()));
    $query->setViewer($this->getRequest()->getUser());
    $query->setLimit(100);
    $stories = $query->execute();

    if (!$stories) {
      return pht('There are no stories about this project.');
    }

    return $this->renderStories($stories);
  }

  private function renderStories(array $stories) {
    assert_instances_of($stories, 'PhabricatorFeedStory');

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($this->getRequest()->getUser());
    $view = $builder->buildView();

    return hsprintf(
      '<div class="phabricator-profile-info-group">'.
        '<h1 class="phabricator-profile-info-header">%s</h1>'.
        '<div class="phabricator-profile-info-pane">%s</div>'.
      '</div>',
      pht('Activity Feed'),
      $view->render());
  }


  private function renderTasksPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $user = $this->getRequest()->getUser();

    $query = id(new ManiphestTaskQuery())
      ->withAnyProjects(array($project->getPHID()))
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setLimit(10)
      ->setCalculateRows(true);
    $tasks = $query->execute();
    $count = $query->getRowCount();

    $phids = mpull($tasks, 'getOwnerPHID');
    $phids = array_merge(
      $phids,
      array_mergev(mpull($tasks, 'getProjectPHIDs')));
    $phids = array_filter($phids);
    $handles = $this->loadViewerHandles($phids);

    $task_list = new ManiphestTaskListView();
    $task_list->setUser($user);
    $task_list->setTasks($tasks);
    $task_list->setHandles($handles);

    $open = number_format($count);

    $more_link = phutil_tag(
      'a',
      array(
        'href' => '/maniphest/view/all/?projects='.$project->getPHID(),
      ),
      pht("View All Open Tasks \xC2\xBB"));

    $content = hsprintf(
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">%s</h1>'.
        '<div class="phabricator-profile-info-pane">'.
          '%s'.
          '<div class="phabricator-profile-info-pane-more-link">%s</div>'.
        '</div>
      </div>',
      pht('Open Tasks (%s)', $open),
      $task_list,
      $more_link);

    return $content;
  }

}
