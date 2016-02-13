<?php

final class PHUICrumbView extends AphrontView {

  private $name;
  private $href;
  private $icon;
  private $isLastCrumb;
  private $workflow;
  private $aural;

  public function setAural($aural) {
    $this->aural = $aural;
    return $this;
  }

  public function getAural() {
    return $this->aural;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  protected function canAppendChild() {
    return false;
  }

  public function setIsLastCrumb($is_last_crumb) {
    $this->isLastCrumb = $is_last_crumb;
    return $this;
  }

  public function render() {
    $classes = array(
      'phui-crumb-view',
    );

    $aural = null;
    if ($this->aural !== null) {
      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        $this->aural);
    }

    $icon = null;
    if ($this->icon) {
      $classes[] = 'phui-crumb-has-icon';
      $icon = id(new PHUIIconView())
        ->setIcon($this->icon);
    }

    // Surround the crumb name with spaces so that double clicking it only
    // selects the crumb itself.
    $name = array(' ', $this->name, ' ');

    $name = phutil_tag(
      'span',
      array(
        'class' => 'phui-crumb-name',
      ),
      $name);

    $divider = null;
    if (!$this->isLastCrumb) {
      $divider = id(new PHUIIconView())
        ->setIcon('fa-angle-right')
        ->addClass('phui-crumb-divider')
        ->addClass('phui-crumb-view');
    } else {
      $classes[] = 'phabricator-last-crumb';
    }

    $tag = javelin_tag(
      $this->href ? 'a' : 'span',
        array(
          'sigil' => $this->workflow ? 'workflow' : null,
          'href'  => $this->href,
          'class' => implode(' ', $classes),
        ),
        array($aural, $icon, $name));

    return array($tag, $divider);
  }
}
