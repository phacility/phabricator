<?php

final class PhabricatorFlagListController extends PhabricatorFlagController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/flag/view/'));
    $nav->addFilter('all', 'Flags');
    $nav->selectFilter('all', 'all');

    $query = new PhabricatorFlagQuery();
    $query->withOwnerPHIDs(array($user->getPHID()));
    $query->setViewer($user);
    $query->needHandles(true);

    $flags = $query->execute();

    $view = new PhabricatorFlagListView();
    $view->setFlags($flags);
    $view->setUser($user);

    $panel = new AphrontPanelView();
    $panel->setHeader('Flags');
    $panel->appendChild($view);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Flags',
      ));
  }

}
