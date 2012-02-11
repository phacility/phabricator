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

class PhabricatorProjectProfileController
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

    $project = id(new PhabricatorProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }
    $profile = $project->loadProfile();
    if (!$profile) {
      $profile = new PhabricatorProjectProfile();
    }

    $src_phid = $profile->getProfileImagePHID();
    if (!$src_phid) {
      $src_phid = $user->getProfileImagePHID();
    }
    $file = id(new PhabricatorFile())->loadOneWhere('phid = %s',
                                                    $src_phid);
    if ($file) {
      $picture = $file->getBestURI();
    } else {
      $picture = null;
    }

    $members = mpull($project->loadAffiliations(), null, 'getUserPHID');

    $nav_view = new AphrontSideNavFilterView();
    $uri = new PhutilURI('/project/view/'.$project->getID().'/');
    $nav_view->setBaseURI($uri);

    $external_arrow = "\xE2\x86\x97";
    $tasks_uri = '/maniphest/view/all/?projects='.$project->getPHID();
    $slug = PhrictionDocument::normalizeSlug($project->getName());
    $phriction_uri = '/w/projects/'.$slug;

    $edit_uri = '/project/edit/'.$project->getID().'/';

    $nav_view->addFilter('dashboard', 'Dashboard');
    $nav_view->addSpacer();
    $nav_view->addFilter('feed', 'Feed');
    $nav_view->addFilter(null, 'Tasks '.$external_arrow, $tasks_uri);
    $nav_view->addFilter(null, 'Wiki '.$external_arrow, $phriction_uri);
    $nav_view->addFilter('people', 'People');
    $nav_view->addFilter('about', 'About');
    $nav_view->addSpacer();
    $nav_view->addFilter(null, "Edit Project\xE2\x80\xA6", $edit_uri);

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
    if (empty($members[$user->getPHID()])) {
      $action = phabricator_render_form(
        $user,
        array(
          'action' => '/project/update/'.$project->getID().'/join/',
          'method' => 'post',
        ),
        phutil_render_tag(
          'button',
          array(
            'class' => 'green',
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

    $phids = array_merge(
      array($project->getAuthorPHID()),
      $project->getSubprojectPHIDs()
    );
    $phids = array_unique($phids);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

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

    if ($project->getSubprojectPHIDs()) {
      $table = $this->renderSubprojectTable(
        $handles,
        $project->getSubprojectPHIDs());
      $subproject_list = $table->render();
    } else {
      $subproject_list = '<p><em>No subprojects.</em></p>';
    }

    $about .=
      '<div class="phabricator-profile-info-group">'.
        '<h1 class="phabricator-profile-info-header">Subprojects</h1>'.
        '<div class="phabricator-profile-info-pane">'.
          $subproject_list.
        '</div>'.
      '</div>';

    return $about;
  }

  private function renderPeoplePage(
    PhabricatorProject $project,
    PhabricatorProjectProfile $profile) {

    $affiliations = $project->loadAffiliations();

    $phids = mpull($affiliations, 'getUserPHID');
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $affiliated = array();
    foreach ($affiliations as $affiliation) {
      $user = $handles[$affiliation->getUserPHID()]->renderLink();
      $role = phutil_escape_html($affiliation->getRole());
      $affiliated[] = '<li>'.$user.' &mdash; '.$role.'</li>';
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
    $stories = $query->execute();

    if (!$stories) {
      return 'There are no stories about this project.';
    }

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $project->getPHID(),
      ));
    $stories = $query->execute();

    return $this->renderStories($stories);
  }

  private function renderStories(array $stories) {

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
      ->withProjects(array($project->getPHID()))
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setLimit(10)
      ->setCalculateRows(true);
    $tasks = $query->execute();
    $count = $query->getRowCount();

    $phids = mpull($tasks, 'getOwnerPHID');
    $phids = array_filter($phids);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

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

  private function renderSubprojectTable(
    PhabricatorObjectHandleData $handles,
    $subprojects_phids) {

    $rows = array();
    foreach ($subprojects_phids as $subproject_phid) {
      $phid = $handles[$subproject_phid]->getPHID();

      $rows[] = array(
        phutil_escape_html($handles[$phid]->getFullName()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small grey button',
            'href' => $handles[$phid]->getURI(),
          ),
          'View Project Profile'),
      );
    }

    $table = new AphrontTableView($rows);
     $table->setHeaders(
       array(
         'Name',
         '',
       ));
     $table->setColumnClasses(
       array(
         'pri',
         'action right',
       ));

    return $table;
  }
}
