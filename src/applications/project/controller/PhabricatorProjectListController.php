<?php

final class PhabricatorProjectListController
  extends PhabricatorProjectController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $nav = $this->buildSideNavView($this->filter);
    $this->filter = $nav->selectFilter($this->filter, 'active');

    $pager = new AphrontPagerView();
    $pager->setPageSize(250);
    $pager->setURI($request->getRequestURI(), 'page');
    $pager->setOffset($request->getInt('page'));

    $query = new PhabricatorProjectQuery();
    $query->setViewer($request->getUser());
    $query->needMembers(true);

    $view_phid = $request->getUser()->getPHID();

    switch ($this->filter) {
      case 'active':
        $table_header = pht('Your Projects');
        $query->withMemberPHIDs(array($view_phid));
        $query->withStatus(PhabricatorProjectQuery::STATUS_ACTIVE);
        break;
      case 'allactive':
        $table_header = pht('Active Projects');
        $query->withStatus(PhabricatorProjectQuery::STATUS_ACTIVE);
        break;
      case 'all':
        $table_header = pht('All Projects');
        $query->withStatus(PhabricatorProjectQuery::STATUS_ANY);
        break;
    }

    $projects = $query->executeWithOffsetPager($pager);

    $project_phids = mpull($projects, 'getPHID');

    $profiles = array();
    if ($projects) {
      $profiles = id(new PhabricatorProjectProfile())->loadAllWhere(
        'projectPHID in (%Ls)',
        $project_phids);
      $profiles = mpull($profiles, null, 'getProjectPHID');
    }

    $tasks = array();
    $groups = array();
    if ($project_phids) {
      $query = id(new ManiphestTaskQuery())
        ->withAnyProjects($project_phids)
        ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
        ->setLimit(PHP_INT_MAX);

      $tasks = $query->execute();
      foreach ($tasks as $task) {
        foreach ($task->getProjectPHIDs() as $phid) {
          $groups[$phid][] = $task;
        }
      }
    }

    $rows = array();
    foreach ($projects as $project) {
      $phid = $project->getPHID();

      $profile = idx($profiles, $phid);
      $members = $project->getMemberPHIDs();

      $group = idx($groups, $phid, array());
      $task_count = count($group);
      $population = count($members);

      if ($profile) {
        $blurb = $profile->getBlurb();
        $blurb = phutil_utf8_shorten($blurb, 64);
      } else {
        $blurb = null;
      }

      $tasks_href = pht('%d Open Task(s)', $task_count);

      $rows[] = array(
        $project->getName(),
        '/project/view/'.$project->getID().'/',
        PhabricatorProjectStatus::getNameForStatus($project->getStatus()),
        PhabricatorProjectStatus::getIconForStatus($project->getStatus()),
        $blurb,
        pht('%d Member(s)', $population),
        phutil_tag(
          'a',
          array(
            'href' => '/maniphest/view/all/?projects='.$phid,
          ),
          $tasks_href),
        '/project/edit/'.$project->getID().'/',
      );
    }

    $list = new PhabricatorObjectItemListView();
    $list->setStackable(true);
    foreach ($rows as $row) {
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($row[0])
        ->setHref($row[1])
        ->addIcon($row[3], $row[2])
        ->addIcon('edit', pht('Edit Project'), $row[7]);
      if ($row[4]) {
        $item->addAttribute($row[4]);
      }
      $item->addAttribute($row[5]);
      $item->addAttribute($row[6]);
      $list->addItem($item);
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader($table_header);

    $nav->appendChild(
      array(
        $header,
        $list,
        $pager,
      ));

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($table_header)
        ->setHref($this->getApplicationURI()));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Projects'),
        'device' => true,
        'dust' => true,
      ));
  }
}
