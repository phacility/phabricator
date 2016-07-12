<?php

final class PhabricatorDashboardPanelListController
  extends PhabricatorDashboardController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $query_key = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($query_key)
      ->setSearchEngine(new PhabricatorDashboardPanelSearchEngine())
      ->setNavigation($this->buildSideNavView());
    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorDashboardPanelSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(pht('Panels'), $this->getApplicationURI().'panel/');

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('fa-plus-square')
        ->setName(pht('Create Panel'))
        ->setHref($this->getApplicationURI().'panel/create/'));

    return $crumbs;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Panel'))
      ->setHref('/dashboard/panel/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Build individual panels to display on your homepage dashboard.'))
      ->addAction($create_button);

      return $view;
  }

}
