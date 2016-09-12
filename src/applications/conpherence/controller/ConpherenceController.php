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
    $crumbs->setBorder(true);

    if (!$is_rooms) {
      $crumbs
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
    $viewer = $this->getViewer();

    $crumbs = $this->buildApplicationCrumbs();
    $data = $conpherence->getDisplayData($this->getViewer());
    $crumbs->addCrumb(
      id(new PHUICrumbView())
      ->setName($data['title'])
      ->setHref('/'.$conpherence->getMonogram()));

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $conpherence,
      PhabricatorPolicyCapability::CAN_EDIT);

    $crumbs
      ->addAction(
        id(new PHUIListItemView())
        ->setName(pht('Edit Room'))
        ->setHref(
          $this->getApplicationURI('update/'.$conpherence->getID()).'/')
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

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
