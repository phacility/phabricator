<?php

final class PhamePostListView extends AphrontTagView {

  private $posts;
  private $nodata;
  private $viewer;
  private $showBlog = false;

  public function setPosts($posts) {
    assert_instances_of($posts, 'PhamePost');
    $this->posts = $posts;
    return $this;
  }

  public function setNodata($nodata) {
    $this->nodata = $nodata;
    return $this;
  }

  public function showBlog($show) {
    $this->showBlog = $show;
    return $this;
  }

  public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  protected function getTagAttributes() {
    return array();
  }

  protected function getTagContent() {
    $viewer = $this->viewer;
    $posts = $this->posts;
    $nodata = $this->nodata;

    $handle_phids = array();
    foreach ($posts as $post) {
      $handle_phids[] = $post->getBloggerPHID();
      if ($post->getBlog()) {
        $handle_phids[] = $post->getBlog()->getPHID();
      }
    }
    $handles = $viewer->loadHandles($handle_phids);

    $list = array();
    foreach ($posts as $post) {
      $blogger = $handles[$post->getBloggerPHID()]->renderLink();
      $blogger_uri = $handles[$post->getBloggerPHID()]->getURI();
      $blogger_image = $handles[$post->getBloggerPHID()]->getImageURI();

      $phame_post = null;
      if ($post->getBody()) {
        $phame_post = PhabricatorMarkupEngine::summarize($post->getBody());
        $phame_post = new PHUIRemarkupView($viewer, $phame_post);
      } else {
        $phame_post = phutil_tag('em', array(), pht('(Empty Post)'));
      }

      $blogger = phutil_tag('strong', array(), $blogger);
      $date = phabricator_datetime($post->getDatePublished(), $viewer);

      $blog = null;
      if ($post->getBlog()) {
        $blog = phutil_tag(
          'a',
          array(
            'href' => '/phame/blog/view/'.$post->getBlog()->getID().'/',
          ),
          $post->getBlog()->getName());
      }

      if ($this->showBlog && $blog) {
        if ($post->isDraft()) {
          $subtitle = pht('Unpublished draft by %s in %s.', $blogger, $blog);
        } else {
          $subtitle = pht('By %s on %s in %s.', $blogger, $date, $blog);
        }
      } else {
        if ($post->isDraft()) {
          $subtitle = pht('Unpublished draft by %s.', $blogger);
        } else {
          $subtitle = pht('Written by %s on %s.', $blogger, $date);
        }
      }

      $item = id(new PHUIDocumentSummaryView())
        ->setTitle($post->getTitle())
        ->setHref('/phame/post/view/'.$post->getID().'/')
        ->setSubtitle($subtitle)
        ->setImage($blogger_image)
        ->setImageHref($blogger_uri)
        ->setSummary($phame_post)
        ->setDraft($post->isDraft());

      $list[] = $item;
    }

    if (empty($list)) {
      $list = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($nodata);
    }

    return $list;
  }

}
