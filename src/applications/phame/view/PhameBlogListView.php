<?php

final class PhameBlogListView extends AphrontTagView {

  private $blogs;

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
    foreach ($this->blogs as $blog) {
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
          'href' => $blog->getViewURI(),
        ),
        $blog->getName());

      $icon = id(new PHUIIconView())
        ->setIcon('fa-plus-square')
        ->addClass('phame-blog-list-icon');

      $add_new = phutil_tag(
        'a',
        array(
          'href' => '/phame/post/edit/?blog='.$blog->getID(),
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
          $add_new,
        ));
    }

    if (empty($list)) {
      $list = phutil_tag(
        'a',
        array(
          'href' => '/phame/blog/edit/',
        ),
        pht('Create a Blog'));
    }

    $header = phutil_tag(
      'h4',
      array(
        'class' => 'phame-blog-list-header',
      ),
      phutil_tag(
        'a',
        array(
          'href' => '/phame/blog/',
        ),
        pht('Blogs')));

    return id(new PHUIBoxView())
      ->appendChild($header)
      ->appendChild($list)
      ->addClass('pl')
      ->setColor(PHUIBoxView::BLUE);

  }

}
