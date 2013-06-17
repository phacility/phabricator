<?php

final class PhabricatorAuthListController
  extends PhabricatorAuthProviderConfigController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = idx($data, 'key');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->key)
      ->setSearchEngine(new PhabricatorAuthProviderConfigSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(array $configs) {
    assert_instances_of($configs, 'PhabricatorAuthProviderConfig');
    $viewer = $this->getRequest()->getUser();

    $list = new PhabricatorObjectItemListView();
    foreach ($configs as $config) {
      $item = new PhabricatorObjectItemView();

      $edit_uri = $this->getApplicationURI('config/edit/'.$config->getID().'/');

      // TODO: Needs to be built out.
      $item
        ->setHeader($config->getProviderType())
        ->setHref($edit_uri);

      $list->addItem($item);
    }

    return $list;
  }

}
