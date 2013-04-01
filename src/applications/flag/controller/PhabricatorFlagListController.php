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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(id(new PhabricatorCrumbView)
      ->setName(pht('Flags'))
      ->setHref($request->getRequestURI()));
    $nav->setCrumbs($crumbs);

    $filter_form = new AphrontFormView();
    $filter_form->setUser($user);
    $filter_form->appendChild(
      id(new AphrontFormToggleButtonsControl())
        ->setName('o')
        ->setLabel(pht('Sort Order'))
        ->setBaseURI($request->getRequestURI(), 'o')
        ->setValue($request->getStr('o', 'n'))
        ->setButtons(
          array(
            'n'   => pht('Date'),
            'c'   => pht('Color'),
            'o'   => pht('Object Type'),
            'r'   => pht('Reason'),
          )));

    $filter = new AphrontListFilterView();
    $filter->appendChild($filter_form);
    $nav->appendChild($filter);

    $query = new PhabricatorFlagQuery();
    $query->withOwnerPHIDs(array($user->getPHID()));
    $query->setViewer($user);
    $query->needHandles(true);

    switch ($request->getStr('o', 'n')) {
      case 'n':
        $order = PhabricatorFlagQuery::ORDER_ID;
        break;
      case 'c':
        $order = PhabricatorFlagQuery::ORDER_COLOR;
        break;
      case 'o':
        $order = PhabricatorFlagQuery::ORDER_OBJECT;
        break;
      case 'r':
        $order = PhabricatorFlagQuery::ORDER_REASON;
        break;
      default:
        throw new Exception("Unknown order!");
    }
    $query->withOrder($order);

    $flags = $query->execute();

    $view = new PhabricatorFlagListView();
    $view->setFlags($flags);
    $view->setUser($user);

    $nav->appendChild($view);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Flags'),
        'dust'  => true,
      ));
  }

}
