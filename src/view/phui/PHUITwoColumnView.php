<?php

final class PHUITwoColumnView extends AphrontTagView {

  private $mainColumn;
  private $sideColumn = null;
  private $navigation;
  private $display;
  private $fluid;
  private $header;
  private $subheader;
  private $footer;
  private $propertySection = array();
  private $curtain;

  const DISPLAY_LEFT = 'phui-side-column-left';
  const DISPLAY_RIGHT = 'phui-side-column-right';

  public function setMainColumn($main) {
    $this->mainColumn = $main;
    return $this;
  }

  public function setSideColumn($side) {
    $this->sideColumn = $side;
    return $this;
  }

  public function setNavigation($nav) {
    $this->navigation = $nav;
    $this->display = self::DISPLAY_LEFT;
    return $this;
  }

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setSubheader($subheader) {
    $this->subheader = $subheader;
    return $this;
  }

  public function setFooter($footer) {
    $this->footer = $footer;
    return $this;
  }

  public function addPropertySection($title, $section) {
    $this->propertySection[] = array(
      'header' => $title,
      'content' => $section,
    );
    return $this;
  }

  public function setCurtain(PHUICurtainView $curtain) {
    $this->curtain = $curtain;
    return $this;
  }

  public function getCurtain() {
    return $this->curtain;
  }

  public function setFluid($fluid) {
    $this->fluid = $fluid;
    return $this;
  }

  public function setDisplay($display) {
    $this->display = $display;
    return $this;
  }

  private function getDisplay() {
    if ($this->display) {
      return $this->display;
    } else {
      return self::DISPLAY_RIGHT;
    }
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-two-column-view';
    $classes[] = $this->getDisplay();

    if ($this->fluid) {
      $classes[] = 'phui-two-column-fluid';
    }

    if ($this->subheader) {
      $classes[] = 'with-subheader';
    }

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-two-column-view-css');

    $main = $this->buildMainColumn();
    $side = $this->buildSideColumn();
    $footer = $this->buildFooter();

    $order = array($side, $main);

    $inner = phutil_tag_div('phui-two-column-row grouped', $order);
    $table = phutil_tag_div('phui-two-column-content', $inner);

    $header = null;
    if ($this->header) {
      $curtain = $this->getCurtain();
      if ($curtain) {
        $action_list = $curtain->getActionList();
        $this->header->setActionListID($action_list->getID());
      }

      $header = phutil_tag_div(
        'phui-two-column-header', $this->header);
    }

    $subheader = null;
    if ($this->subheader) {
      $subheader = phutil_tag_div(
        'phui-two-column-subheader', $this->subheader);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-two-column-container',
      ),
      array(
        $header,
        $subheader,
        $table,
        $footer,
      ));
  }

  private function buildMainColumn() {

    $view = array();
    $sections = $this->propertySection;

    if ($sections) {
      foreach ($sections as $section) {
        $section_header = $section['header'];

        $section_content = $section['content'];
        if ($section_content === null) {
          continue;
        }

        if ($section_header instanceof PHUIHeaderView) {
          $header = $section_header;
        } else {
          $header = id(new PHUIHeaderView())
            ->setHeader($section_header);
        }

        $view[] = id(new PHUIObjectBoxView())
          ->setHeader($header)
          ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
          ->appendChild($section_content);
      }
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-main-column',
      ),
      array(
        $view,
        $this->mainColumn,
      ));
  }

  private function buildSideColumn() {

    $classes = array();
    $classes[] = 'phui-side-column';
    $navigation = null;
    if ($this->navigation) {
      $classes[] = 'side-has-nav';
      $navigation = id(new PHUIObjectBoxView())
        ->appendChild($this->navigation);
    }

    $curtain = $this->getCurtain();

    return phutil_tag(
      'div',
      array(
        'class' => implode($classes, ' '),
      ),
      array(
        $navigation,
        $curtain,
        $this->sideColumn,
      ));
  }

  private function buildFooter() {

    $footer = $this->footer;

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-two-column-content phui-two-column-footer',
      ),
      array(
        $footer,
      ));

  }
}
