<?php

final class PhabricatorCrumbsView extends AphrontView {

  private $crumbs = array();
  private $actions = array();

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
      id(new PhabricatorCrumbView())
        ->setName($text)
        ->setHref($href));
  }

  public function addCrumb(PhabricatorCrumbView $crumb) {
    $this->crumbs[] = $crumb;
    return $this;
  }

  public function addAction(PHUIListItemView $action) {
    $this->actions[] = $action;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-crumbs-view-css');

    $action_view = null;
    if ($this->actions) {
      $actions = array();
      foreach ($this->actions as $action) {
        $icon = null;
        if ($action->getIcon()) {
          $icon_name = $action->getIcon();
          if ($action->getDisabled()) {
            $icon_name .= ' lightgreytext';
          }

          $icon = id(new PHUIIconView())
            ->setIconFont($icon_name);

        }
        $name = phutil_tag(
          'span',
            array(
              'class' => 'phabricator-crumbs-action-name',
            ),
          $action->getName());

        $action_sigils = $action->getSigils();
        if ($action->getWorkflow()) {
          $action_sigils[] = 'workflow';
        }
        $action_classes = $action->getClasses();
        $action_classes[] = 'phabricator-crumbs-action';

        if ($action->getDisabled()) {
          $action_classes[] = 'phabricator-crumbs-action-disabled';
        }

        $actions[] = javelin_tag(
          'a',
          array(
            'href' => $action->getHref(),
            'class' => implode(' ', $action_classes),
            'sigil' => implode(' ', $action_sigils),
            'style' => $action->getStyle(),
          ),
          array(
            $icon,
            $name,
          ));
      }

      $action_view = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-crumbs-actions',
        ),
        $actions);
    }

    if ($this->crumbs) {
      last($this->crumbs)->setIsLastCrumb(true);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-crumbs-view '.
                   'sprite-gradient gradient-breadcrumbs',
      ),
      array(
        $action_view,
        $this->crumbs,
      ));
  }

}
