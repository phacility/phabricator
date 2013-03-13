<?php

/**
 * @group maniphest
 */
abstract class ManiphestController extends PhabricatorController {

  private $defaultQuery;

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Maniphest'));
    $page->setBaseURI('/maniphest/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\x93");
    $page->appendPageObjects(idx($data, 'pageObjects', array()));
    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_OPEN_TASKS);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function buildBaseSideNav($filter = null, $for_app = false) {
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
      $nav->addFilter('saved',  pht('Edit...'), '/maniphest/custom/');
    }

    if ($for_app) {
      $nav->addFilter('', pht('Create Task'),
        $this->getApplicationURI('task/create/'));
    }

    $nav->addLabel(pht('User Tasks'));
    $nav->addFilter('action', pht('Assigned'));
    $nav->addFilter('created', pht('Created'));
    $nav->addFilter('subscribed', pht('Subscribed'));
    $nav->addFilter('triage', pht('Need Triage'));
    $nav->addLabel(pht('User Projects'));
    $nav->addFilter('projecttriage', pht('Need Triage'));
    $nav->addFilter('projectall', pht('All Tasks'));
    $nav->addLabel('All Tasks');
    $nav->addFilter('alltriage', pht('Need Triage'));
    $nav->addFilter('all', pht('All Tasks'));
    $nav->addLabel(pht('Custom'));
    $nav->addFilter('custom', pht('Custom Query'));
    $nav->addFilter('report', pht('Reports'), '/maniphest/report/');

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildBaseSideNav(null, true)->getMenu();
  }

  protected function getDefaultQuery() {
    return $this->defaultQuery;
  }

}
