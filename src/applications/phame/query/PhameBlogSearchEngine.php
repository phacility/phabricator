<?php

final class PhameBlogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Phame Blogs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhameApplication';
  }

  public function newQuery() {
    return new PhameBlogQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    return '/phame/blog/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All'),
    );
    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }
  protected function renderResultList(
    array $blogs,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($blogs, 'PhameBlog');
    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);

    foreach ($blogs as $blog) {
      $id = $blog->getID();
      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($blog)
        ->setHeader($blog->getName())
        ->setStatusIcon('fa-star')
        ->setHref($this->getApplicationURI("/blog/view/{$id}/"))
        ->addAttribute($blog->getSkin())
        ->addAttribute($blog->getDomain());
      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No blogs found.'));

    return $result;
  }

}
