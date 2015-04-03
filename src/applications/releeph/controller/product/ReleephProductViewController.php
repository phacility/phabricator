<?php

final class ReleephProductViewController extends ReleephProductController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $productID;
  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->productID = idx($data, 'projectID');
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $product = id(new ReleephProductQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->productID))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }
    $this->setProduct($product);

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($this->queryKey)
      ->setPreface($this->renderPreface())
      ->setSearchEngine(
        id(new ReleephBranchSearchEngine())
          ->setProduct($product))
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $branches,
    PhabricatorSavedQuery $saved) {
    assert_instances_of($branches, 'ReleephBranch');

    $viewer = $this->getRequest()->getUser();

    $products = mpull($branches, 'getProduct');
    $repo_phids = mpull($products, 'getRepositoryPHID');

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withPHIDs($repo_phids)
      ->execute();
    $repos = mpull($repos, null, 'getPHID');

    $requests = array();
    if ($branches) {
      $requests = id(new ReleephRequestQuery())
        ->setViewer($viewer)
        ->withBranchIDs(mpull($branches, 'getID'))
        ->withStatus(ReleephRequestQuery::STATUS_OPEN)
        ->execute();
      $requests = mgroup($requests, 'getBranchID');
    }

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($branches as $branch) {
      $diffusion_href = null;
      $repo = idx($repos, $branch->getProduct()->getRepositoryPHID());
      if ($repo) {
        $drequest = DiffusionRequest::newFromDictionary(
          array(
            'user' => $viewer,
            'repository' => $repo,
          ));

        $diffusion_href = $drequest->generateURI(
          array(
            'action' => 'branch',
            'branch' => $branch->getName(),
          ));
      }

      $branch_link = $branch->getName();
      if ($diffusion_href) {
        $branch_link = phutil_tag(
          'a',
          array(
            'href' => $diffusion_href,
          ),
          $branch_link);
      }

      $item = id(new PHUIObjectItemView())
        ->setHeader($branch->getDisplayName())
        ->setHref($this->getApplicationURI('branch/'.$branch->getID().'/'))
        ->addAttribute($branch_link);

      if (!$branch->getIsActive()) {
        $item->setDisabled(true);
      }

      $commit = $branch->getCutPointCommit();
      if ($commit) {
        $item->addIcon(
          'none',
          phabricator_datetime($commit->getEpoch(), $viewer));
      }

      $open_count = count(idx($requests, $branch->getID(), array()));
      if ($open_count) {
        $item->setBarColor('orange');
        $item->addIcon(
          'fa-code-fork',
          pht('%d Open Pull Request(s)', new PhutilNumber($open_count)));
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $viewer = $this->getRequest()->getUser();
    $product = $this->getProduct();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('product/create/', pht('Create Product'));
    }

    id(new ReleephBranchSearchEngine())
      ->setProduct($product)
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $product = $this->getProduct();
    if ($product) {
      $crumbs->addAction(
        id(new PHUIListItemView())
          ->setHref($product->getURI('cutbranch/'))
          ->setName(pht('Cut New Branch'))
          ->setIcon('fa-plus'));
    }

    return $crumbs;
  }

  private function renderPreface() {
    $viewer = $this->getRequest()->getUser();
    $product = $this->getProduct();

    $id = $product->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader($product->getName())
      ->setUser($viewer)
      ->setPolicyObject($product);

    if ($product->getIsActive()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Inactive'));
    }

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($product)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $product,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI("product/{$id}/edit/");
    $history_uri = $this->getApplicationURI("product/{$id}/history/");

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Product'))
        ->setHref($edit_uri)
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($product->getIsActive()) {
      $status_name = pht('Deactivate Product');
      $status_href = "product/{$id}/action/deactivate/";
      $status_icon = 'fa-times';
    } else {
      $status_name = pht('Reactivate Product');
      $status_href = "product/{$id}/action/activate/";
      $status_icon = 'fa-plus-circle-o';
    }

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName($status_name)
        ->setHref($this->getApplicationURI($status_href))
        ->setIcon($status_icon)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setHref($history_uri)
        ->setIcon('fa-list'));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($product);

    $properties->addProperty(
      pht('Repository'),
      $product->getRepository()->getName());

    $properties->setActionList($actions);

    $pushers = $product->getPushers();
    if ($pushers) {
      $properties->addProperty(
        pht('Pushers'),
        $viewer->renderHandleList($pushers));
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

}
