<?php

final class PonderFooterView extends AphrontTagView {

  private $contentID;
  private $count;
  private $actions = array();

  public function setContentID($content_id) {
    $this->contentID = $content_id;
    return $this;
  }

  public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  public function addAction($action) {
    $this->actions[] = $action;
    return $this;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'ponder-footer-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('ponder-view-css');
    Javelin::initBehavior('phabricator-reveal-content');

    $hide_action_id = celerity_generate_unique_node_id();
    $show_action_id = celerity_generate_unique_node_id();
    $content_id = $this->contentID;

    if ($this->count == 0) {
      $icon = id(new PHUIIconView())
        ->setIconFont('fa-comments msr');
      $text = pht('Add a Comment');
    } else {
      $icon = id(new PHUIIconView())
        ->setIconFont('fa-comments msr');
      $text = pht('Show %d Comment(s)', new PhutilNumber($this->count));
    }

    $actions = array();
    $hide_action = javelin_tag(
      'a',
      array(
        'sigil' => 'reveal-content',
        'class' => 'ponder-footer-action',
        'id' => $hide_action_id,
        'href' => '#',
        'meta' => array(
          'showIDs' => array($content_id, $show_action_id),
          'hideIDs' => array($hide_action_id),
        ),
      ),
      array($icon, $text));

    $show_action = javelin_tag(
      'a',
      array(
        'sigil' => 'reveal-content',
        'style' => 'display: none;',
        'class' => 'ponder-footer-action',
        'id' => $show_action_id,
        'href' => '#',
        'meta' => array(
          'showIDs' => array($hide_action_id),
          'hideIDs' => array($content_id, $show_action_id),
        ),
      ),
      array($icon, pht('Hide Comments')));

    $actions[] = $hide_action;
    $actions[] = $show_action;

    return array($actions, $this->actions);
  }

}
