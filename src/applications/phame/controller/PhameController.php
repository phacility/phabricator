<?php

/**
 * @group phame
 */
abstract class PhameController extends PhabricatorController {

  protected function renderSideNavFilterView() {

    $base_uri = new PhutilURI($this->getApplicationURI());

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI($base_uri);

    $nav->addLabel(pht('Create'));
    $nav->addFilter('post/new', pht('New Post'));
    $nav->addFilter('blog/new', pht('New Blog'));

    $nav->addLabel(pht('Posts'));
    $nav->addFilter('post/draft', pht('My Drafts'));
    $nav->addFilter('post', pht('My Posts'));
    $nav->addFilter('post/all', pht('All Posts'));

    $nav->addLabel(pht('Blogs'));
    $nav->addFilter('blog/user', pht('Joinable Blogs'));
    $nav->addFilter('blog/all', pht('All Blogs'));

    $nav->selectFilter(null);

    return $nav;
  }

  protected function renderPostList(
    array $posts,
    PhabricatorUser $user,
    $nodata) {
    assert_instances_of($posts, 'PhamePost');

    $stories = array();

    foreach ($posts as $post) {
      $blogger = $this->getHandle($post->getBloggerPHID())->renderLink();
      $bloggerURI = $this->getHandle($post->getBloggerPHID())->getURI();
      $bloggerImage = $this->getHandle($post->getBloggerPHID())->getImageURI();

      $blog = null;
      if ($post->getBlog()) {
        $blog = $this->getHandle($post->getBlog()->getPHID())->renderLink();
      }

      $phame_post = '';
      if ($post->getBody()) {
        $phame_post = PhabricatorMarkupEngine::summarize($post->getBody());
      }

      $blog_view = $post->getViewURI();
      $phame_title = phutil_tag('a', array('href' => $blog_view),
        $post->getTitle());

      $blogger = phutil_tag('strong', array(), $blogger);
      if ($post->isDraft()) {
        $title = pht('%s drafted a blog post on %s.',
          $blogger, $blog);
        $title = phutil_tag('em', array(), $title);
      } else {
        $title = pht('%s wrote a blog post on %s.',
          $blogger, $blog);
      }

      $item = id(new PhabricatorObjectItemView())
        ->setObject($post)
        ->setHeader($post->getTitle())
        ->setHref($this->getApplicationURI('post/view/'.$post->getID().'/'));

      $story = id(new PHUIFeedStoryView())
        ->setTitle($title)
        ->setImage($bloggerImage)
        ->setImageHref($bloggerURI)
        ->setAppIcon('phame-dark')
        ->setUser($user)
        ->setPontification($phame_post, $phame_title);

      if ($post->getDatePublished()) {
        $story->setEpoch($post->getDatePublished());
      }
      $stories[] = $story;
    }

    return $stories;
  }

  public function buildApplicationMenu() {
    return $this->renderSideNavFilterView()->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blog'))
        ->setHref($this->getApplicationURI('/blog/new'))
        ->setIcon('create'));
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Post'))
        ->setHref($this->getApplicationURI('/post/new'))
        ->setIcon('new'));
    return $crumbs;
  }
}
