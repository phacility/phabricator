<?php

abstract class ConpherenceController extends PhabricatorController {

  private $conpherence;

  public function setConpherence(ConpherenceThread $conpherence) {
    $this->conpherence = $conpherence;
    return $this;
  }
  public function getConpherence() {
    return $this->conpherence;
  }

  public function buildApplicationMenu() {
    $nav = new PHUIListView();

    $nav->newLink(
      pht('New Room'),
      $this->getApplicationURI('new/'));

    $nav->addMenuItem(
      id(new PHUIListItemView())
      ->setName(pht('Add Participants'))
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setHref('#')
      ->addSigil('conpherence-widget-adder')
      ->setMetadata(array('widget' => 'widgets-people')));

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    return $this->buildConpherenceApplicationCrumbs();
  }

  protected function buildConpherenceApplicationCrumbs($is_rooms = false) {
    $crumbs = parent::buildApplicationCrumbs();

    if ($is_rooms) {
      $crumbs
        ->addAction(
          id(new PHUIListItemView())
          ->setName(pht('New Room'))
          ->setHref($this->getApplicationURI('new/'))
          ->setIcon('fa-plus-square')
          ->setWorkflow(true));
    } else {
      $crumbs
        ->addAction(
          id(new PHUIListItemView())
          ->setName(pht('New Room'))
          ->setHref($this->getApplicationURI('new/'))
          ->setIcon('fa-plus-square')
          ->setWorkflow(true))
        ->addAction(
          id(new PHUIListItemView())
          ->setName(pht('Room'))
          ->setHref('#')
          ->setIcon('fa-bars')
          ->setStyle('display: none;')
          ->addClass('device-widgets-selector')
          ->addSigil('device-widgets-selector'));
    }
    return $crumbs;
  }

  protected function buildHeaderPaneContent(
    ConpherenceThread $conpherence,
    array $policy_objects) {
    assert_instances_of($policy_objects, 'PhabricatorPolicy');

    $crumbs = $this->buildApplicationCrumbs();
    $data = $conpherence->getDisplayData($this->getViewer());
    $crumbs->addCrumb(
      id(new PHUICrumbView())
      ->setName($data['title'])
      ->setHref('/'.$conpherence->getMonogram()));

    return hsprintf(
      '%s',
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'header-loading-mask',
          ),
          ''),
        $crumbs,
      ));
  }

}
