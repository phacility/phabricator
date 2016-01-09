<?php

final class PhamePostSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Phame Posts');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhameApplication';
  }

  public function newQuery() {
    return new PhamePostQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if (strlen($map['visibility'])) {
      $query->withVisibility($map['visibility']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchSelectField())
        ->setKey('visibility')
        ->setLabel(pht('Visibility'))
        ->setOptions(
          array(
            '' => pht('All'),
            PhameConstants::VISIBILITY_PUBLISHED => pht('Published'),
            PhameConstants::VISIBILITY_DRAFT => pht('Draft'),
          )),
    );
  }

  protected function getURI($path) {
    return '/phame/post/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Posts'),
      'live' => pht('Published Posts'),
      'draft' => pht('Draft Posts'),
    );
    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'live':
        return $query->setParameter(
          'visibility', PhameConstants::VISIBILITY_PUBLISHED);
      case 'draft':
        return $query->setParameter(
          'visibility', PhameConstants::VISIBILITY_DRAFT);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }


  protected function renderResultList(
    array $posts,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($posts, 'PhamePost');
    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);

    foreach ($posts as $post) {
      $id = $post->getID();
      $blog = $post->getBlog();

      $blog_name = $viewer->renderHandle($post->getBlogPHID())->render();
      $blog_name = pht('Blog: %s', $blog_name);

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($post)
        ->setHeader($post->getTitle())
        ->setStatusIcon('fa-star')
        ->setHref($post->getViewURI())
        ->addAttribute($blog_name);
      if ($post->isDraft()) {
        $item->setStatusIcon('fa-star-o grey');
        $item->setDisabled(true);
        $item->addIcon('none', pht('Draft Post'));
      } else {
        $date = $post->getDatePublished();
        $item->setEpoch($date);
      }
      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No blogs posts found.'));

    return $result;
  }

}
