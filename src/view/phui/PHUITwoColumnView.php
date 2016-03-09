<?php

final class PHUITwoColumnView extends AphrontTagView {

  private $mainColumn;
  private $sideColumn = null;
  private $display;
  private $fluid;
  private $header;
  private $subheader;
  private $propertySection = array();
  private $actionList;
  private $propertyList;
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

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setSubheader($subheader) {
    $this->subheader = $subheader;
    return $this;
  }

  public function addPropertySection($title, $section) {
    $this->propertySection[] = array($title, $section);
    return $this;
  }

  public function setActionList(PhabricatorActionListView $list) {
    $this->actionList = $list;
    return $this;
  }

  public function setPropertyList(PHUIPropertyListView $list) {
    $this->propertyList = $list;
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
    $order = array($side, $main);

    $inner = phutil_tag_div('phui-two-column-row grouped', $order);
    $table = phutil_tag_div('phui-two-column-content', $inner);

    $header = null;
    if ($this->header) {
      $curtain = $this->getCurtain();
      if ($curtain) {
        $action_list = $curtain->getActionList();
      } else {
        $action_list = $this->actionList;
      }

      if ($action_list) {
        $this->header->setActionList($action_list);
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
      ));
  }

  private function buildMainColumn() {

    $view = array();
    $sections = $this->propertySection;

    if ($sections) {
      foreach ($sections as $content) {
        if ($content[1]) {
          $view[] = id(new PHUIObjectBoxView())
            ->setHeaderText($content[0])
            ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
            ->appendChild($content[1]);
        }
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
    $property_list = $this->propertyList;
    $action_list = $this->actionList;

    $properties = null;
    if ($property_list || $action_list) {
      if ($property_list) {
        $property_list->setStacked(true);
      }

      $properties = id(new PHUIObjectBoxView())
        ->appendChild($action_list)
        ->appendChild($property_list)
        ->addClass('phui-two-column-properties');
    }

    $curtain = $this->getCurtain();

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-side-column',
      ),
      array(
        $properties,
        $curtain,
        $this->sideColumn,
      ));
  }
}
