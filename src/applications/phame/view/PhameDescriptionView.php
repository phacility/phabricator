<?php

final class PhameDescriptionView extends AphrontTagView {

  private $title;
  private $description;
  private $image;
  private $imageHref;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function setImageHref($href) {
    $this->imageHref = $href;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phame-blog-description';
    return array('class' => implode(' ', $classes));
  }

  protected function getTagContent() {
    require_celerity_resource('phame-css');

    $description = phutil_tag_div(
      'phame-blog-description-content', $this->description);

    $image = phutil_tag(
      ($this->imageHref) ? 'a' : 'div',
      array(
        'class' => 'phame-blog-description-image',
        'style' => 'background-image: url('.$this->image.');',
        'href' => $this->imageHref,
      ));

    $header = phutil_tag(
      'div',
      array(
        'class' => 'phame-blog-description-name',
      ),
      $this->title);

    return array($image, $header, $description);
  }

}
