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
    return id(new PhameBlogQuery())
      ->needProfileImage(true);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    if ($map['statuses']) {
      $query->withStatuses(array($map['statuses']));
    }
    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchSelectField())
        ->setKey('statuses')
        ->setLabel(pht('Status'))
        ->setOptions(array(
          '' => pht('All'),
          PhameBlog::STATUS_ACTIVE => pht('Active'),
          PhameBlog::STATUS_ARCHIVED => pht('Archived'),
          )),
    );
  }

  protected function getURI($path) {
    return '/phame/blog/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active Blogs'),
      'archived' => pht('Archived Blogs'),
      'all' => pht('All Blogs'),
    );
    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query->setParameter(
          'statuses', PhameBlog::STATUS_ACTIVE);
      case 'archived':
        return $query->setParameter(
          'statuses', PhameBlog::STATUS_ARCHIVED);
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
      if ($blog->getDomain()) {
        $domain = $blog->getDomain();
      } else {
        $domain = pht('Local Blog');
      }
      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($blog)
        ->setHeader($blog->getName())
        ->setImageURI($blog->getProfileImageURI())
        ->setDisabled($blog->isArchived())
        ->setHref($this->getApplicationURI("/blog/view/{$id}/"))
        ->addAttribute($domain);
      if (!$blog->isArchived()) {
        $button = id(new PHUIButtonView())
          ->setTag('a')
          ->setText('New Post')
          ->setHref($this->getApplicationURI('/post/edit/?blog='.$id));
        $item->setLaunchButton($button);
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No blogs found.'));

    return $result;
  }

}
