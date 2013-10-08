<?php

final class PHUIStatusItemView extends AphrontTagView {

  private $icon;
  private $iconLabel;
  private $target;
  private $note;
  private $highlighted;

  public function setIcon($icon, $label = null) {
    $this->icon = $icon;
    $this->iconLabel = $label;
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
        ->setSpriteSheet(PHUIIconView::SPRITE_STATUS)
        ->setSpriteIcon($this->icon);

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
