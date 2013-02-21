<?php

/**
 * @group maniphest
 */
abstract class ManiphestController extends PhabricatorController {

  private $defaultQuery;

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Maniphest');
    $page->setBaseURI('/maniphest/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\x93");
    $page->appendPageObjects(idx($data, 'pageObjects', array()));
    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_OPEN_TASKS);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function buildBaseSideNav() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/maniphest/view/'));

    $request = $this->getRequest();
    $user = $request->getUser();

    $custom = id(new ManiphestSavedQuery())->loadAllWhere(
      'userPHID = %s ORDER BY isDefault DESC, name ASC',
      $user->getPHID());

    // TODO: Enforce uniqueness. Currently, it's possible to save the same
    // query under multiple names, and then SideNavFilterView explodes on
    // duplicate keys. Generally, we should clean up the custom/saved query
    // code as it's a bit of a mess.
    $custom = mpull($custom, null, 'getQueryKey');

    if ($custom) {
      $nav->addLabel('Saved Queries');
      foreach ($custom as $query) {
        if ($query->getIsDefault()) {
          $this->defaultQuery = $query;
        }
        $nav->addFilter(
          'Q:'.$query->getQueryKey(),
          $query->getName(),
          '/maniphest/view/custom/?key='.$query->getQueryKey());
      }
      $nav->addFilter('saved',  'Edit...', '/maniphest/custom/');
    }

    $nav->addLabel('User Tasks');
    $nav->addFilter('action',       'Assigned');
    $nav->addFilter('created',      'Created');
    $nav->addFilter('subscribed',   'Subscribed');
    $nav->addFilter('triage',       'Need Triage');
    $nav->addLabel('User Projects');
    $nav->addFilter('projecttriage', 'Need Triage');
    $nav->addFilter('projectall',   'All Tasks');
    $nav->addLabel('All Tasks');
    $nav->addFilter('alltriage',    'Need Triage');
    $nav->addFilter('all',          'All Tasks');
    $nav->addLabel('Custom');
    $nav->addFilter('custom',       'Custom Query');
    $nav->addLabel('Reports');
    $nav->addFilter('report',       'Reports', '/maniphest/report/');

    return $nav;
  }

  protected function getDefaultQuery() {
    return $this->defaultQuery;
  }

}
