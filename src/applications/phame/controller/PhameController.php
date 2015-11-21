<?php

abstract class PhameController extends PhabricatorController {

  protected function renderPostList(
    array $posts,
    PhabricatorUser $viewer,
    $nodata) {
    assert_instances_of($posts, 'PhamePost');

    $handle_phids = array();
    foreach ($posts as $post) {
      $handle_phids[] = $post->getBloggerPHID();
      if ($post->getBlog()) {
        $handle_phids[] = $post->getBlog()->getPHID();
      }
    }
    $handles = $viewer->loadHandles($handle_phids);

    $stories = array();
    foreach ($posts as $post) {
      $blogger = $handles[$post->getBloggerPHID()]->renderLink();
      $blogger_uri = $handles[$post->getBloggerPHID()]->getURI();
      $blogger_image = $handles[$post->getBloggerPHID()]->getImageURI();

      $blog = null;
      if ($post->getBlog()) {
        $blog = $handles[$post->getBlog()->getPHID()]->renderLink();
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
        $title = pht(
          '%s drafted a blog post on %s.',
          $blogger,
          $blog);
        $title = phutil_tag('em', array(), $title);
      } else {
        $title = pht(
          '%s wrote a blog post on %s.',
          $blogger,
          $blog);
      }

      $item = id(new PHUIObjectItemView())
        ->setObject($post)
        ->setHeader($post->getTitle())
        ->setHref($this->getApplicationURI('post/view/'.$post->getID().'/'));

      $story = id(new PHUIFeedStoryView())
        ->setTitle($title)
        ->setImage($blogger_image)
        ->setImageHref($blogger_uri)
        ->setAppIcon('fa-star')
        ->setUser($viewer)
        ->setPontification($phame_post, $phame_title);

      if (PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $post,
        PhabricatorPolicyCapability::CAN_EDIT)) {

        $story->addAction(id(new PHUIIconView())
          ->setHref($this->getApplicationURI('post/edit/'.$post->getID().'/'))
          ->setIconFont('fa-pencil'));
      }

      if ($post->getDatePublished()) {
        $story->setEpoch($post->getDatePublished());
      }

      $stories[] = $story;
    }

    if (empty($stories)) {
      return id(new PHUIBoxView())
        ->appendChild($nodata)
        ->addClass('mlt mlb msr msl');
    }

    return $stories;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      PhameBlogCreateCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blog'))
        ->setHref($this->getApplicationURI('/blog/new/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Post'))
        ->setHref($this->getApplicationURI('/post/new/'))
        ->setIcon('fa-pencil'));
    return $crumbs;
  }
}
