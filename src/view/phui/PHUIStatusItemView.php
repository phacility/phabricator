<?php

final class PHUIStatusItemView extends AphrontTagView {

  private $icon;
  private $iconLabel;
  private $iconColor;
  private $target;
  private $note;
  private $highlighted;

  const ICON_ACCEPT = 'fa-check-circle';
  const ICON_REJECT = 'fa-times-circle';
  const ICON_LEFT = 'fa-chevron-circle-left';
  const ICON_RIGHT = 'fa-chevron-circle-right';
  const ICON_UP = 'fa-chevron-circle-up';
  const ICON_DOWN = 'fa-chevron-circle-down';
  const ICON_QUESTION = 'fa-question-circle';
  const ICON_WARNING = 'fa-exclamation-circle';
  const ICON_INFO = 'fa-info-circle';
  const ICON_ADD = 'fa-plus-circle';
  const ICON_MINUS = 'fa-minus-circle';
  const ICON_OPEN = 'fa-circle-o';
  const ICON_CLOCK = 'fa-clock-o';
  const ICON_STAR = 'fa-star';

  /* render_textarea */
  public function setIcon($icon, $color = null, $label = null) {
    $this->icon = $icon;
    $this->iconLabel = $label;
    $this->iconColor = $color;
    return $this;
  }

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function setNote($note) {
    $this->note = $note;
    return $this;
  }

  public function setHighlighted($highlighted) {
    $this->highlighted = $highlighted;
    return $this;
  }

  protected function canAppendChild() {
    return false;
  }

  protected function getTagName() {
    return 'tr';
  }

  protected function getTagAttributes() {
    $classes = array();
    if ($this->highlighted) {
      $classes[] = 'phui-status-item-highlighted';
    }

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {

    $icon = null;
    if ($this->icon) {
      $icon = id(new PHUIIconView())
        ->setIconFont($this->icon.' '.$this->iconColor);

      if ($this->iconLabel) {
        Javelin::initBehavior('phabricator-tooltips');
        $icon->addSigil('has-tooltip');
        $icon->setMetadata(
          array(
            'tip' => $this->iconLabel,
            'size' => 240,
          ));
      }
    }

    $icon_cell = phutil_tag(
      'td',
      array(),
      $icon);

    $target_cell = phutil_tag(
      'td',
      array(
        'class' => 'phui-status-item-target',
      ),
      $this->target);

    $note_cell = phutil_tag(
      'td',
      array(
        'class' => 'phui-status-item-note',
      ),
      $this->note);

    return array(
      $icon_cell,
      $target_cell,
      $note_cell,
    );
  }
}
