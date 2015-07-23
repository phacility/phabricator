<?php

final class PHUIDocumentView extends AphrontTagView {

  /* For mobile displays, where do you want the sidebar */
  const NAV_BOTTOM = 'nav_bottom';
  const NAV_TOP = 'nav_top';

  private $offset;
  private $header;
  private $sidenav;
  private $topnav;
  private $crumbs;
  private $bookname;
  private $bookdescription;
  private $mobileview;
  private $fluid;

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function setHeader(PHUIHeaderView $header) {
    $header->setTall(true);
    $this->header = $header;
    return $this;
  }

  public function setSideNav(PHUIListView $list, $display = self::NAV_BOTTOM) {
    $list->setType(PHUIListView::SIDENAV_LIST);
    $this->sidenav = $list;
    $this->mobileview = $display;
    return $this;
  }

  public function setTopNav(PHUIListView $list) {
    $list->setType(PHUIListView::NAVBAR_LIST);
    $this->topnav = $list;
    return $this;
  }

  public function setCrumbs(PHUIListView $list) {
    $this->crumbs  = $list;
    return $this;
  }

  public function setBook($name, $description) {
    $this->bookname = $name;
    $this->bookdescription = $description;
    return $this;
  }

  public function setFluid($fluid) {
    $this->fluid = $fluid;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();

    if ($this->offset) {
      $classes[] = 'phui-document-offset';
    }

    if ($this->fluid) {
      $classes[] = 'phui-document-fluid';
    }

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-document-view-css');

    $classes = array();
    $classes[] = 'phui-document-view';
    if ($this->offset) {
      $classes[] = 'phui-offset-view';
    }
    if ($this->sidenav) {
      $classes[] = 'phui-sidenav-view';
    }

    $sidenav = null;
    if ($this->sidenav) {
      $sidenav = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-sidenav',
        ),
        $this->sidenav);
    }

    $book = null;
    if ($this->bookname) {
      $book = pht('%s (%s)', $this->bookname, $this->bookdescription);
    }

    $topnav = null;
    if ($this->topnav) {
      $topnav = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-topnav',
        ),
        $this->topnav);
    }

    $crumbs = null;
    if ($this->crumbs) {
      $crumbs = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-crumbs',
        ),
        $this->bookName);
    }

    $main_content = $this->renderChildren();

    if ($book) {
      $this->header->setSubheader($book);
    }
    $content_inner = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-inner',
        ),
        array(
          $this->header,
          $topnav,
          $main_content,
          $crumbs,
        ));

    if ($this->mobileview == self::NAV_BOTTOM) {
      $order = array($content_inner, $sidenav);
    } else {
      $order = array($sidenav, $content_inner);
    }

    $content = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-content',
        ),
        $order);

    $view = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $content);

    return $view;
  }

}
