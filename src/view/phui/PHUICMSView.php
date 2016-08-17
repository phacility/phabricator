<?php

final class PHUICMSView extends AphrontTagView {

  private $header;
  private $nav;
  private $crumbs;
  private $content;
  private $toc;
  private $comments;

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setNavigation(AphrontSideNavFilterView $nav) {
    $this->nav = $nav;
    return $this;
  }

  public function setCrumbs(PHUICrumbsView $crumbs) {
    $this->crumbs = $crumbs;
    return $this;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function setToc($toc) {
    $this->toc = $toc;
    return $this;
  }

  public function setComments($comments) {
    $this->comments = $comments;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-cms-css');

    $classes = array();
    $classes[] = 'phui-cms-view';

    if ($this->comments) {
      $classes[] = 'phui-cms-has-comments';
    }

    return array(
        'class' => implode(' ', $classes),
      );

  }

  protected function getTagContent() {

    $content = phutil_tag(
      'div',
      array(
        'class' => 'phui-cms-page-content',
      ),
      array(
        $this->header,
        $this->content,
      ));

    $comments = null;
    if ($this->comments) {
      $comments = phutil_tag(
        'div',
        array(
          'class' => 'phui-cms-comments',
        ),
        array(
          $this->comments,
        ));
    }

    $navigation = $this->nav;
    $navigation->appendChild($content);
    $navigation->appendChild($comments);

    $page = phutil_tag(
      'div',
      array(
        'class' => 'phui-cms-inner',
      ),
      array(
        $navigation,
      ));

    $cms_view = phutil_tag(
      'div',
      array(
        'class' => 'phui-cms-wrap',
      ),
      array(
        $this->crumbs,
        $page,
      ));

    $classes = array();
    $classes[] = 'phui-cms-page';

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $cms_view);
  }
}
