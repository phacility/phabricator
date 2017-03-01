<?php

final class AlmanacConsoleController extends AlmanacController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Devices'))
        ->setHref($this->getApplicationURI('device/'))
        ->setImageIcon('fa-server')
        ->addAttribute(
          pht(
            'Create an inventory of physical and virtual hosts and '.
            'devices.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Services'))
        ->setHref($this->getApplicationURI('service/'))
        ->setImageIcon('fa-plug')
        ->addAttribute(
          pht(
            'Create and update services, and map them to interfaces on '.
            'devices.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Networks'))
        ->setHref($this->getApplicationURI('network/'))
        ->setImageIcon('fa-globe')
        ->addAttribute(
          pht(
            'Manage public and private networks.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Namespaces'))
        ->setHref($this->getApplicationURI('namespace/'))
        ->setImageIcon('fa-asterisk')
        ->addAttribute(
          pht('Control who can create new named services and devices.')));

    $docs_uri = PhabricatorEnv::getDoclink(
      'Almanac User Guide');

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Documentation'))
        ->setHref($docs_uri)
        ->setImageIcon('fa-book')
        ->addAttribute(pht('Browse documentation for Almanac.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));
    $crumbs->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setObjectList($menu);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Almanac Console'))
      ->setHeaderIcon('fa-server');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle(pht('Almanac Console'))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
