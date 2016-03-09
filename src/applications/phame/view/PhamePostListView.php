<?php

final class PhamePostListView extends AphrontTagView {

  private $posts;
  private $nodata;
  private $showBlog = false;
  private $isExternal;
  private $isLive;

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

  public function setIsExternal($is_external) {
    $this->isExternal = $is_external;
    return $this;
  }

  public function getIsExternal() {
    return $this->isExternal;
  }

  public function setIsLive($is_live) {
    $this->isLive = $is_live;
    return $this;
  }

  public function getIsLive() {
    return $this->isLive;
  }

  protected function getTagAttributes() {
    return array();
  }

  protected function getTagContent() {
    $viewer = $this->getViewer();
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

      $blog = $post->getBlog();

      if ($this->getIsLive()) {
        if ($this->getIsExternal()) {
          $blog_uri = $blog->getExternalLiveURI();
          $post_uri = $post->getExternalLiveURI();
        } else {
          $blog_uri = $blog->getInternalLiveURI();
          $post_uri = $post->getInternalLiveURI();
        }
      } else {
        $blog_uri = $blog->getViewURI();
        $post_uri = $post->getViewURI();
      }

      $blog_link = phutil_tag(
        'a',
        array(
          'href' => $blog_uri,
        ),
        $blog->getName());

      if ($this->showBlog) {
        if ($post->isDraft()) {
          $subtitle = pht(
            'Unpublished draft by %s in %s.',
            $blogger,
            $blog_link);
        } else {
          $subtitle = pht(
            'Written by %s on %s in %s.',
            $blogger,
            $date,
            $blog_link);
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
        ->setHref($post_uri)
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
