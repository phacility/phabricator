<?php

final class PhameNextPostView extends AphrontTagView {

  private $nextTitle;
  private $nextHref;
  private $previousTitle;
  private $previousHref;

  public function setNext($title, $href) {
    $this->nextTitle = $title;
    $this->nextHref = $href;
    return $this;
  }

  public function setPrevious($title, $href) {
    $this->previousTitle = $title;
    $this->previousHref = $href;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phame-next-post-view';
    $classes[] = 'grouped';
    return array('class' => implode(' ', $classes));
  }

  protected function getTagContent() {
    require_celerity_resource('phame-css');

    $p_icon = id(new PHUIIconView())
      ->setIcon('fa-angle-left');

    $previous_icon = phutil_tag(
      'div',
      array(
        'class' => 'phame-previous-arrow',
      ),
      $p_icon);

    $previous_text = phutil_tag(
      'div',
      array(
        'class' => 'phame-previous-header',
      ),
      pht('Previous Post'));

    $previous_title = phutil_tag(
      'div',
      array(
        'class' => 'phame-previous-title',
      ),
      $this->previousTitle);

    $previous = null;
    if ($this->previousHref) {
      $previous = phutil_tag(
        'a',
        array(
          'class' => 'phame-previous',
          'href' => $this->previousHref,
        ),
        array(
          $previous_icon,
          $previous_text,
          $previous_title,
        ));
    }

    $n_icon = id(new PHUIIconView())
      ->setIcon('fa-angle-right');

    $next_icon = phutil_tag(
      'div',
      array(
        'class' => 'phame-next-arrow',
      ),
      $n_icon);

    $next_text = phutil_tag(
      'div',
      array(
        'class' => 'phame-next-header',
      ),
      pht('Next Post'));

    $next_title = phutil_tag(
      'div',
      array(
        'class' => 'phame-next-title',
      ),
      $this->nextTitle);

    $next = null;
    if ($this->nextHref) {
      $next = phutil_tag(
        'a',
        array(
          'class' => 'phame-next',
          'href' => $this->nextHref,
        ),
        array(
          $next_icon,
          $next_text,
          $next_title,
        ));
    }

    return array($previous, $next);
  }

}
