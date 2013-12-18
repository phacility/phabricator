<?php

final class HeraldRuleListController extends HeraldController
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
      ->setSearchEngine(new HeraldRuleSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $rules,
    PhabricatorSavedQuery $query) {
    assert_instances_of($rules, 'HeraldRule');

    $viewer = $this->getRequest()->getUser();

    $phids = mpull($rules, 'getAuthorPHID');
    $this->loadHandles($phids);

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($viewer);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($rules as $rule) {
      $id = $rule->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName("H{$id}")
        ->setHeader($rule->getName())
        ->setHref($this->getApplicationURI("rule/{$id}/"));

      if ($rule->isPersonalRule()) {
        $item->addByline(
          pht(
            'Authored by %s',
            $this->getHandle($rule->getAuthorPHID())->renderLink()));
      } else {
        $item->addIcon('world', pht('Global Rule'));
      }

      if ($rule->getIsDisabled()) {
        $item->setDisabled(true);
        $item->addIcon('disable-grey', pht('Disabled'));
      }

      $item->addAction(
        id(new PHUIListItemView())
          ->setHref($this->getApplicationURI("history/{$id}/"))
          ->setIcon('transcript')
          ->setName(pht('Edit Log')));

      $content_type_name = idx($content_type_map, $rule->getContentType());
      $item->addAttribute(pht('Affects: %s', $content_type_name));

      $list->addItem($item);
    }

    return $list;
  }

}
