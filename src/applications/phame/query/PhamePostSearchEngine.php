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

    if ($map['visibility']) {
      $query->withVisibility($map['visibility']);
    }
    if ($map['blogPHIDs']) {
      $query->withBlogPHIDs($map['blogPHIDs']);
    }


    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('visibility')
        ->setLabel(pht('Visibility'))
        ->setOptions(
          array(
            PhameConstants::VISIBILITY_PUBLISHED => pht('Published'),
            PhameConstants::VISIBILITY_DRAFT => pht('Draft'),
            PhameConstants::VISIBILITY_ARCHIVED => pht('Archived'),
          )),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Blogs'))
        ->setKey('blogPHIDs')
        ->setAliases(array('blog', 'blogs', 'blogPHIDs'))
        ->setDescription(
          pht('Search for posts within certain blogs.'))
        ->setDatasource(new PhameBlogDatasource()),

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
      'archived' => pht('Archived Posts'),
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
          'visibility', array(PhameConstants::VISIBILITY_PUBLISHED));
      case 'draft':
        return $query->setParameter(
          'visibility', array(PhameConstants::VISIBILITY_DRAFT));
      case 'archived':
        return $query->setParameter(
          'visibility', array(PhameConstants::VISIBILITY_ARCHIVED));
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
        ->setObjectName($post->getMonogram())
        ->setHeader($post->getTitle())
        ->setStatusIcon('fa-star')
        ->setHref($post->getViewURI())
        ->addAttribute($blog_name);
      if ($post->isDraft()) {
        $item->setStatusIcon('fa-star-o grey');
        $item->setDisabled(true);
        $item->addIcon('fa-star-o', pht('Draft Post'));
      } else if ($post->isArchived()) {
        $item->setStatusIcon('fa-ban grey');
        $item->setDisabled(true);
        $item->addIcon('fa-ban', pht('Archived Post'));
      } else {
        $date = $post->getDatePublished();
        $item->setEpoch($date);
      }
      $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-pencil')
            ->setHref($post->getEditURI()));
      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No blogs posts found.'));

    return $result;
  }

}
