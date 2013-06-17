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

      $id = $config->getID();

      $edit_uri = $this->getApplicationURI('config/edit/'.$id.'/');
      $enable_uri = $this->getApplicationURI('config/enable/'.$id.'/');
      $disable_uri = $this->getApplicationURI('config/disable/'.$id.'/');

      // TODO: Needs to be built out.
      $item
        ->setHeader($config->getProviderType())
        ->setHref($edit_uri);

      if ($config->getIsEnabled()) {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('delete')
            ->setHref($disable_uri)
            ->addSigil('workflow'));
      } else {
        $item->setBarColor('grey');
        $item->addIcon('delete-grey', pht('Disabled'));
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('new')
            ->setHref($enable_uri)
            ->addSigil('workflow'));
      }

      $list->addItem($item);
    }

    return $list;
  }

}
