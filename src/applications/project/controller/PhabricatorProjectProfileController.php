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

final class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  private $id;
  private $page;

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

        $content .= $this->renderStories($stories);
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

    $content = '<div style="padding: 1em;">'.$content.'</div>';
    $nav_view->appendChild($content);

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

      $action = phabricator_render_form(
        $user,
        array(
          'action' => '/project/update/'.$project->getID().'/join/',
          'method' => 'post',
        ),
        phutil_render_tag(
          'button',
          array(
            'class' => $class,
          ),
          'Join Project'));
    } else {
      $action = javelin_render_tag(
        'a',
        array(
          'href'  => '/project/update/'.$project->getID().'/leave/',
          'sigil' => 'workflow',
          'class' => 'grey button',
        ),
        'Leave Project...');
    }

    $header->addAction($action);

    $header->appendChild($nav_view);

    return $this->buildStandardPageResponse(
      $header,
      array(
        'title' => $project->getName().' Project',
      ));
  }

  private function renderAboutPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $viewer = $this->getRequest()->getUser();

    $blurb = $profile->getBlurb();
    $blurb = phutil_escape_html($blurb);
    $blurb = str_replace("\n", '<br />', $blurb);

    $phids = array($project->getAuthorPHID());
    $phids = array_unique($phids);
    $handles = $this->loadViewerHandles($phids);

    $timestamp = phabricator_datetime($project->getDateCreated(), $viewer);

    $about =
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">About</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>Creator</th>
              <td>'.$handles[$project->getAuthorPHID()]->renderLink().'</td>
            </tr>
            <tr>
              <th>Created</th>
              <td>'.$timestamp.'</td>
            </tr>
            <tr>
              <th>PHID</th>
              <td>'.phutil_escape_html($project->getPHID()).'</td>
            </tr>
            <tr>
              <th>Blurb</th>
              <td>'.$blurb.'</td>
            </tr>
          </table>
        </div>
      </div>';

    return $about;
  }

  private function renderPeoplePage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $member_phids = $project->getMemberPHIDs();
    $handles = $this->loadViewerHandles($member_phids);

    $affiliated = array();
    foreach ($handles as $phids => $handle) {
      $affiliated[] = '<li>'.$handle->renderLink().'</li>';
    }

    if ($affiliated) {
      $affiliated = '<ul>'.implode("\n", $affiliated).'</ul>';
    } else {
      $affiliated = '<p><em>No one is affiliated with this project.</em></p>';
    }

    return
      '<div class="phabricator-profile-info-group">'.
        '<h1 class="phabricator-profile-info-header">People</h1>'.
        '<div class="phabricator-profile-info-pane">'.
         $affiliated.
        '</div>'.
      '</div>';
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
      return 'There are no stories about this project.';
    }

    return $this->renderStories($stories);
  }

  private function renderStories(array $stories) {
    assert_instances_of($stories, 'PhabricatorFeedStory');

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($this->getRequest()->getUser());
    $view = $builder->buildView();

    return
      '<div class="phabricator-profile-info-group">'.
        '<h1 class="phabricator-profile-info-header">Activity Feed</h1>'.
        '<div class="phabricator-profile-info-pane">'.
         $view->render().
        '</div>'.
      '</div>';
  }


  private function renderTasksPage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $query = id(new ManiphestTaskQuery())
      ->withAnyProjects(array($project->getPHID()))
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setLimit(10)
      ->setCalculateRows(true);
    $tasks = $query->execute();
    $count = $query->getRowCount();

    $phids = mpull($tasks, 'getOwnerPHID');
    $phids = array_filter($phids);
    $handles = $this->loadViewerHandles($phids);

    $task_views = array();
    foreach ($tasks as $task) {
      $view = id(new ManiphestTaskSummaryView())
        ->setTask($task)
        ->setHandles($handles)
        ->setUser($this->getRequest()->getUser());
      $task_views[] = $view->render();
    }

    if (empty($tasks)) {
      $task_views = '<em>No open tasks.</em>';
    } else {
      $task_views = implode('', $task_views);
    }

    $open = number_format($count);

    $more_link = phutil_render_tag(
      'a',
      array(
        'href' => '/maniphest/view/all/?projects='.$project->getPHID(),
      ),
      "View All Open Tasks \xC2\xBB");

    $content =
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">'.
          "Open Tasks ({$open})".
        '</h1>'.
        '<div class="phabricator-profile-info-pane">'.
          $task_views.
          '<div class="phabricator-profile-info-pane-more-link">'.
            $more_link.
          '</div>'.
        '</div>
      </div>';

    return $content;
  }

}
