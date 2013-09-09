<?php

final class PhabricatorFlagListController extends PhabricatorFlagController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorFlagSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $flags,
    PhabricatorSavedQuery $query) {
    assert_instances_of($flags, 'PhabricatorFlag');

    $viewer = $this->getRequest()->getUser();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($flags as $flag) {
      $id = $flag->getID();
      $phid = $flag->getObjectPHID();

      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());

      $flag_icon = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-flag-icon '.$class,
        ),
        '');

      $item = id(new PHUIObjectItemView())
        ->addHeadIcon($flag_icon)
        ->setHeader($flag->getHandle()->renderLink());

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('edit')
          ->setHref($this->getApplicationURI("edit/{$phid}/"))
          ->setWorkflow(true));

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('delete')
          ->setHref($this->getApplicationURI("delete/{$id}/"))
          ->setWorkflow(true));

      if ($flag->getNote()) {
        $item->addAttribute($flag->getNote());
      }

      $item->addIcon(
        'none',
        phabricator_datetime($flag->getDateCreated(), $viewer));

      $list->addItem($item);
    }

    return $list;
  }

}
