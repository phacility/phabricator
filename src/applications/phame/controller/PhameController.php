<?php

/**
 * @group phame
 */
abstract class PhameController extends PhabricatorController {

  protected function renderSideNavFilterView() {

    $base_uri = new PhutilURI($this->getApplicationURI());

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI($base_uri);

    $nav->addLabel('Create');
    $nav->addFilter('post/new',   'New Post');
    $nav->addFilter('blog/new',   'New Blog');

    $nav->addLabel('Posts');
    $nav->addFilter('post/draft', 'My Drafts');
    $nav->addFilter('post',       'My Posts');
    $nav->addFilter('post/all',   'All Posts');

    $nav->addLabel('Blogs');
    $nav->addFilter('blog/user',  'Joinable Blogs');
    $nav->addFilter('blog/all',   'All Blogs');

    $nav->selectFilter(null);

    return $nav;
  }

  protected function renderPostList(
    array $posts,
    PhabricatorUser $user,
    $nodata) {
    assert_instances_of($posts, 'PhamePost');

    $list = id(new PhabricatorObjectItemListView())
      ->setUser($user)
      ->setNoDataString($nodata);

    foreach ($posts as $post) {
      $blogger = $this->getHandle($post->getBloggerPHID())->renderLink();

      $blog = null;
      if ($post->getBlog()) {
        $blog = $this->getHandle($post->getBlog()->getPHID())->renderLink();
      }

      $published = null;
      if ($post->getDatePublished()) {
        $published = phabricator_date($post->getDatePublished(), $user);
      }

      $draft = $post->isDraft();

      $item = id(new PhabricatorObjectItemView())
        ->setObject($post)
        ->setHeader($post->getTitle())
        ->setHref($this->getApplicationURI('post/view/'.$post->getID().'/'));

      if ($blog) {
        $item->addAttribute($blog);
      }

      if ($draft) {
        $desc = pht('Draft by %s', $blogger);
      } else {
        $desc = pht('Published on %s by %s', $published, $blogger);
      }
      $item->addAttribute($desc);

      $list->addItem($item);
    }

    return $list;
  }
}
