<?php

final class PhabricatorCrumbView extends AphrontView {

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
      'phabricator-crumb-view',
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
      $classes[] = 'phabricator-crumb-has-icon';
      $icon = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-crumb-icon '.
                     'sprite-apps-large apps-'.$this->icon.'-dark-large',
        ),
        '');
    }

    $name = phutil_tag(
      'span',
      array(
        'class' => 'phabricator-crumb-name',
      ),
      $this->name);

    $divider = null;
    if (!$this->isLastCrumb) {
      $divider = phutil_tag(
        'span',
        array(
          'class' => 'sprite-menu phabricator-crumb-divider',
        ),
        '');
    } else {
      $classes[] = 'phabricator-last-crumb';
    }

    return javelin_tag(
      $this->href ? 'a' : 'span',
        array(
          'sigil' => $this->workflow ? 'workflow' : null,
          'href'  => $this->href,
          'class' => implode(' ', $classes),
        ),
        array($aural, $icon, $name, $divider));
  }
}
