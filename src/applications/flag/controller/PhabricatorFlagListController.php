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

    $filter_form = new AphrontFormView();
    $filter_form->setNoShading(true);
    $filter_form->setUser($user);
    $filter_form->appendChild(
      id(new AphrontFormToggleButtonsControl())
        ->setName('o')
        ->setLabel(pht('Sort Order'))
        ->setBaseURI($request->getRequestURI(), 'o')
        ->setValue($flag_order)
        ->setButtons(
          array(
            'n'   => pht('Date'),
            'c'   => pht('Color'),
            'o'   => pht('Object Type'),
          )));

    $filter = new AphrontListFilterView();
    $filter->appendChild($filter_form);
    $nav->appendChild($filter);

    $query = new PhabricatorFlagQuery();
    $query->withOwnerPHIDs(array($user->getPHID()));
    $query->setViewer($user);
    $query->needHandles(true);

    switch ($flag_order) {
      //   'r'
      //   'a'
      case 'n':
        $order = PhabricatorFlagQuery::ORDER_ID;
        break;
      case 'c':
        $order = PhabricatorFlagQuery::ORDER_COLOR;
        break;
      case 'o':
        $order = PhabricatorFlagQuery::ORDER_OBJECT;
        break;
      default:
        throw new Exception("Unknown order!");
    }
    $query->withOrder($order);

    $flags = $query->execute();

    $views = array();
    if ($flag_order == 'n') {
      $view = new PhabricatorFlagListView();
      $view->setFlags($flags);
      $view->setUser($user);
      $view->setFlush(true);
      $views[] = array(
        'view'  => $view,
      );
    } else {
      switch ($flag_order) {
        case 'c':
          $flags_tmp = mgroup($flags, 'getColor');
          $flags = array();
          foreach ($flags_tmp as $color => $flag_group) {
            $title = pht('%s Flags',
              PhabricatorFlagColor::getColorName($color));
            $flags[$title] = $flag_group;
          }
          break;
        case 'o':
          $flags_tmp = mgroup($flags, 'getType');
          $flags = array();
          foreach ($flags_tmp as $color => $flag_group) {
            // Appending an 's' to fake plurals
            $title = head($flag_group)->getHandle()->getTypeName() . 's';
            $flags[$title] = $flag_group;
          }
          break;
        default:
          throw new Exception("Unknown order!");
      }

      foreach ($flags as $group_title => $flag_group) {
        $view = new PhabricatorFlagListView();
        $view->setFlags($flag_group);
        $view->setUser($user);
        $view->setFlush(true);
        $views[] = array(
          'title' => pht('%s (%d)', $group_title, count($flag_group)),
          'view'  => $view,
        );
      }
    }

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
