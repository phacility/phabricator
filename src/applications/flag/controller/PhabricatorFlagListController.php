<?php

final class PhabricatorFlagListController extends PhabricatorFlagController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $flag_order = $request->getStr('o', 'n');

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/flag/view/'));
    $nav->addLabel(pht('Flags'));
    $nav->addFilter('all', pht('Your Flags'));
    $nav->selectFilter('all', 'all');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(id(new PhabricatorCrumbView)
      ->setName(pht('Flags'))
      ->setHref($request->getRequestURI()));
    $nav->setCrumbs($crumbs);

    $query = new PhabricatorFlagQuery();
    $query->withOwnerPHIDs(array($user->getPHID()));
    $query->setViewer($user);
    $query->needHandles(true);

    $flags = $query->execute();

    $views = array();
    $view = new PhabricatorFlagListView();
    $view->setFlags($flags);
    $view->setUser($user);
    $view->setFlush(true);
    $views[] = array(
      'view'  => $view,
    );

    foreach ($views as $view) {
      $panel = new AphrontPanelView();
      $panel->setNoBackground();

      $title = idx($view, 'title');
      if ($title) {
        $panel->setHeader($title);
      }
      $panel->appendChild($view['view']);
      $nav->appendChild($panel);
    }

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Flags'),
        'device' => true,
        'dust'  => true,
      ));
  }

}
