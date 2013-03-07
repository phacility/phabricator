<?php

final class PhabricatorFlagListController extends PhabricatorFlagController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/flag/view/'));
    $nav->addLabel(pht('Flags'));
    $nav->addFilter('all', pht('Your Flags'));
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
    $panel->setHeader(pht('Flags'));
    $panel->appendChild($view);
    $panel->setNoBackground();

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => pht('Flags'),
      ));
  }

}
