<?php

final class PassphraseCredentialListController extends PassphraseController
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
      ->setSearchEngine(new PassphraseCredentialSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $credentials,
    PhabricatorSavedQuery $query) {
    assert_instances_of($credentials, 'PassphraseCredential');

    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($credentials as $credential) {

      $item = id(new PHUIObjectItemView())
        ->setObjectName('K'.$credential->getID())
        ->setHeader($credential->getName())
        ->setHref('/K'.$credential->getID())
        ->setObject($credential);

      $item->addAttribute(
        pht('Login: %s', $credential->getUsername()));

      if ($credential->getIsDestroyed()) {
        $item->addIcon('disable', pht('Destroyed'));
        $item->setDisabled(true);
      }

      $type = PassphraseCredentialType::getTypeByConstant(
        $credential->getCredentialType());
      if ($type) {
        $item->addIcon('wrench', $type->getCredentialTypeName());
      }

      $list->addItem($item);
    }

    return $list;
  }

}
