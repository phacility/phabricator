<?php

final class AphrontListFilterView extends AphrontView {

  private $showAction;
  private $hideAction;
  private $showHideDescription;
  private $showHideHref;

  public function setCollapsed($show, $hide, $description, $href) {
    $this->showAction = $show;
    $this->hideAction = $hide;
    $this->showHideDescription = $description;
    $this->showHideHref = $href;
    return $this;
  }

  public function render() {
    $content = $this->renderChildren();
    if (!$content) {
      return null;
    }

    require_celerity_resource('aphront-list-filter-view-css');

    $content = phutil_tag(
      'div',
      array(
        'class' => 'aphront-list-filter-view-content',
      ),
      $content);

    $classes = array();
    $classes[] = 'aphront-list-filter-view';
    if ($this->showAction !== null) {
      $classes[] = 'aphront-list-filter-view-collapsible';

      Javelin::initBehavior('phabricator-reveal-content');

      $hide_action_id = celerity_generate_unique_node_id();
      $show_action_id = celerity_generate_unique_node_id();
      $content_id = celerity_generate_unique_node_id();

      $hide_action = javelin_tag(
        'a',
        array(
          'class' => 'button button-grey',
          'sigil' => 'reveal-content',
          'id' => $hide_action_id,
          'href' => $this->showHideHref,
          'meta' => array(
            'hideIDs' => array($hide_action_id),
            'showIDs' => array($content_id, $show_action_id),
          ),
        ),
        $this->showAction);

      $content_description = phutil_tag(
        'div',
        array(
          'class' => 'aphront-list-filter-description',
        ),
        $this->showHideDescription);

      $show_action = javelin_tag(
        'a',
        array(
          'class' => 'button button-grey',
          'sigil' => 'reveal-content',
          'style' => 'display: none;',
          'href' => '#',
          'id' => $show_action_id,
          'meta' => array(
            'hideIDs' => array($content_id, $show_action_id),
            'showIDs' => array($hide_action_id),
          ),
        ),
        $this->hideAction);

      $reveal_block = phutil_tag(
        'div',
        array(
          'class' => 'aphront-list-filter-reveal',
        ),
        array(
          $content_description,
          $hide_action,
          $show_action,
        ));

      $content = array(
        $reveal_block,
        phutil_tag(
          'div',
          array(
            'id' => $content_id,
            'style' => 'display: none;',
          ),
          $content),
      );
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $content);

    return phutil_tag(
      'div',
      array(
        'class' => 'aphront-list-filter-wrap',
      ),
      $content);
  }

}
