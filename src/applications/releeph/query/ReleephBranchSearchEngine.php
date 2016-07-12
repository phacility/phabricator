<?php

final class ReleephBranchSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $product;

  public function getResultTypeDescription() {
    return pht('Releeph Branches');
  }

  public function getApplicationClassName() {
    return 'PhabricatorReleephApplication';
  }

  public function setProduct(ReleephProject $product) {
    $this->product = $product;
    return $this;
  }

  public function getProduct() {
    return $this->product;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('active', $request->getStr('active'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ReleephBranchQuery())
      ->needCutPointCommits(true)
      ->withProductPHIDs(array($this->getProduct()->getPHID()));

    $active = $saved->getParameter('active');
    $value = idx($this->getActiveValues(), $active);
    if ($value !== null) {
      $query->withStatus($value);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form->appendChild(
      id(new AphrontFormSelectControl())
        ->setName('active')
        ->setLabel(pht('Show Branches'))
        ->setValue($saved_query->getParameter('active'))
        ->setOptions($this->getActiveOptions()));
  }

  protected function getURI($path) {
    return '/releeph/product/'.$this->getProduct()->getID().'/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open'),
      'all' => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'open':
        return $query
          ->setParameter('active', 'open');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getActiveOptions() {
    return array(
      'open' => pht('Open Branches'),
      'all' => pht('Open and Closed Branches'),
    );
  }

  private function getActiveValues() {
    return array(
      'open' => ReleephBranchQuery::STATUS_OPEN,
      'all' => ReleephBranchQuery::STATUS_ALL,
    );
  }

  protected function renderResultList(
    array $branches,
    PhabricatorSavedQuery $query,
    array $handles) {


    assert_instances_of($branches, 'ReleephBranch');

    $viewer = $this->getRequest()->getUser();

    $products = mpull($branches, 'getProduct');
    $repo_phids = mpull($products, 'getRepositoryPHID');

    if ($repo_phids) {
      $repos = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withPHIDs($repo_phids)
        ->execute();
      $repos = mpull($repos, null, 'getPHID');
    } else {
      $repos = array();
    }

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
        $item->setStatusIcon('fa-code-fork orange');
        $item->addIcon(
          'fa-code-fork',
          pht(
            '%s Open Pull Request(s)',
            new PhutilNumber($open_count)));
      }

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list);
  }
}
