<?php

final class PhabricatorMainMenuIconView extends AphrontView {

  private $classes = array();
  private $href;
  private $name;
  private $sortOrder = 0.5;
  private $workflow;
  private $style;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function addStyle($style) {
    $this->style = $style;
    return $this;
  }

  /**
   * Provide a float, where 0.0 is the profile item and 1.0 is the logout
   * item. Normally you should pick something between the two.
   *
   * @param float Sort order.
   * @return this
   */
  public function setSortOrder($sort_order) {
    $this->sortOrder = $sort_order;
    return $this;
  }

  public function getSortOrder() {
    return $this->sortOrder;
  }

  public function render() {
    $name = $this->getName();
    $href = $this->getHref();

    $classes = $this->classes;
    $classes[] = 'phabricator-main-menu-icon';

    $label = javelin_render_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'phabricator-main-menu-icon-label',
      ),
      phutil_escape_html($name));

    $item = javelin_render_tag(
      'a',
      array(
        'href' => $href,
        'class' => implode(' ', $classes),
        'style' => $this->style,
        'sigil' => $this->workflow ? 'workflow' : null,
      ),
      '');

    $group = new PhabricatorMainMenuGroupView();
    $group->appendChild($item);
    $group->appendChild($label);

    return $group->render();
  }

}
