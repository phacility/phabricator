<?php

final class PhameDraftListView extends AphrontTagView {

  private $posts;
  private $blogs;

  public function setPosts($posts) {
    assert_instances_of($posts, 'PhamePost');
    $this->posts = $posts;
    return $this;
  }

  public function setBlogs($blogs) {
    assert_instances_of($blogs, 'PhameBlog');
    $this->blogs = $blogs;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phame-blog-list';
    return array('class' => implode(' ', $classes));
  }

  protected function getTagContent() {
    require_celerity_resource('phame-css');

    $list = array();
    foreach ($this->posts as $post) {
      $blog = $post->getBlog();
      $image_uri = $blog->getProfileImageURI();
      $image = phutil_tag(
        'a',
        array(
          'class' => 'phame-blog-list-image',
          'style' => 'background-image: url('.$image_uri.');',
          'href' => $blog->getViewURI(),
        ));

      $title = phutil_tag(
        'a',
        array(
          'class' => 'phame-blog-list-title',
          'href' => $post->getViewURI(),
        ),
        $post->getTitle());

      $icon = id(new PHUIIconView())
        ->setIcon('fa-pencil-square-o')
        ->addClass('phame-blog-list-icon');

      $edit = phutil_tag(
        'a',
        array(
          'href' => '/phame/post/edit/'.$post->getID().'/',
          'class' => 'phame-blog-list-new-post',
        ),
        $icon);

      $list[] = phutil_tag(
        'div',
        array(
          'class' => 'phame-blog-list-item',
        ),
        array(
          $image,
          $title,
          $edit,
        ));
    }

    if (empty($list)) {
      $list = pht('You have no draft posts.');
    }

    $header = phutil_tag(
      'h4',
      array(
        'class' => 'phame-blog-list-header',
      ),
      phutil_tag(
        'a',
        array(
          'href' => '/phame/post/query/draft/',
        ),
        pht('Drafts')));

    return id(new PHUIBoxView())
      ->appendChild($header)
      ->appendChild($list)
      ->addClass('pl')
      ->setColor(PHUIBoxView::BLUE);
  }

}
