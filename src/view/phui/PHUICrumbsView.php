<?php

final class PHUICrumbsView extends AphrontView {

  private $crumbs = array();
  private $actions = array();
  private $border;

  protected function canAppendChild() {
    return false;
  }


  /**
   * Convenience method for adding a simple crumb with just text, or text and
   * a link.
   *
   * @param string  Text of the crumb.
   * @param string? Optional href for the crumb.
   * @return this
   */
  public function addTextCrumb($text, $href = null) {
    return $this->addCrumb(
      id(new PHUICrumbView())
        ->setName($text)
        ->setHref($href));
  }

  public function addCrumb(PHUICrumbView $crumb) {
    $this->crumbs[] = $crumb;
    return $this;
  }

  public function addAction(PHUIListItemView $action) {
    $this->actions[] = $action;
    return $this;
  }

  public function setBorder($border) {
    $this->border = $border;
    return $this;
  }

  public function getActions() {
    return $this->actions;
  }

  public function render() {
    require_celerity_resource('phui-crumbs-view-css');

    $action_view = null;
    if ($this->actions) {
      $actions = array();
      foreach ($this->actions as $action) {
      if ($action->getType() == PHUIListItemView::TYPE_DIVIDER) {
          $actions[] = phutil_tag(
            'span',
            array(
              'class' => 'phui-crumb-action-divider',
            ));
          continue;
        }

        $icon = null;
        if ($action->getIcon()) {
          $icon_name = $action->getIcon();
          if ($action->getDisabled()) {
            $icon_name .= ' lightgreytext';
          }

          $icon = id(new PHUIIconView())
            ->setIcon($icon_name);

        }

        $action_classes = $action->getClasses();
        $action_classes[] = 'phui-crumbs-action';

        $name = null;
        if ($action->getName()) {
          $name = phutil_tag(
            'span',
              array(
                'class' => 'phui-crumbs-action-name',
              ),
            $action->getName());
        } else {
          $action_classes[] = 'phui-crumbs-action-icon';
        }

        $action_sigils = $action->getSigils();
        if ($action->getWorkflow()) {
          $action_sigils[] = 'workflow';
        }

        if ($action->getDisabled()) {
          $action_classes[] = 'phui-crumbs-action-disabled';
        }

        $actions[] = javelin_tag(
          'a',
          array(
            'href' => $action->getHref(),
            'class' => implode(' ', $action_classes),
            'sigil' => implode(' ', $action_sigils),
            'style' => $action->getStyle(),
            'meta' => $action->getMetadata(),
          ),
          array(
            $icon,
            $name,
          ));
      }

      $action_view = phutil_tag(
        'div',
        array(
          'class' => 'phui-crumbs-actions',
        ),
        $actions);
    }

    if ($this->crumbs) {
      last($this->crumbs)->setIsLastCrumb(true);
    }

    $classes = array();
    $classes[] = 'phui-crumbs-view';
    if ($this->border) {
      $classes[] = 'phui-crumbs-border';
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $action_view,
        $this->crumbs,
      ));
  }

}
