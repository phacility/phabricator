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
    $conpherence = $this->conpherence;

    // Local Links
    if ($conpherence) {
      $nav->addMenuItem(
        id(new PHUIListItemView())
        ->setName(pht('Edit Room'))
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref(
          $this->getApplicationURI('update/'.$conpherence->getID()).'/')
        ->setWorkflow(true));

      $nav->addMenuItem(
        id(new PHUIListItemView())
        ->setName(pht('Add Participants'))
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref('#')
        ->addSigil('conpherence-widget-adder')
        ->setMetadata(array('widget' => 'widgets-people')));
    }

    // Global Links
    $nav->newLabel(pht('Conpherence'));
    $nav->newLink(
      pht('New Room'),
      $this->getApplicationURI('new/'));
    $nav->newLink(
      pht('Search Rooms'),
      $this->getApplicationURI('search/'));

    return $nav;
  }

  protected function buildHeaderPaneContent(
    ConpherenceThread $conpherence,
    array $policy_objects) {
    assert_instances_of($policy_objects, 'PhabricatorPolicy');
    $viewer = $this->getViewer();
    $header = null;

    if ($conpherence->getID()) {
      $data = $conpherence->getDisplayData($this->getViewer());
      $header = id(new PHUIHeaderView())
        ->setHeader($data['title'])
        ->setSubheader($data['topic'])
        ->addClass((!$data['topic']) ? 'conpherence-no-topic' : null);

      $can_edit = PhabricatorPolicyFilter::hasCapability(
          $viewer,
          $conpherence,
          PhabricatorPolicyCapability::CAN_EDIT);

      $header->addActionItem(
        id(new PHUIIconCircleView())
          ->setHref(
            $this->getApplicationURI('update/'.$conpherence->getID()).'/')
          ->setIcon('fa-pencil')
          ->addClass('hide-on-device')
          ->setColor('violet')
          ->setWorkflow(true));

      $header->addActionItem(
        id(new PHUIIconCircleView())
          ->setHref(
            $this->getApplicationURI('update/'.$conpherence->getID()).'/'.
            '?action='.ConpherenceUpdateActions::NOTIFICATIONS)
          ->setIcon('fa-gear')
          ->addClass('hide-on-device')
          ->setColor('pink')
          ->setWorkflow(true));

      $widget_key = PhabricatorConpherenceWidgetVisibleSetting::SETTINGKEY;
      $widget_view = (bool)$viewer->getUserSetting($widget_key, false);

      Javelin::initBehavior(
        'toggle-widget',
        array(
          'show' => (int)$widget_view,
          'settingsURI' => '/settings/adjust/?key='.$widget_key,
        ));

      $header->addActionItem(
        id(new PHUIIconCircleView())
          ->addSigil('conpherence-widget-toggle')
          ->setIcon('fa-group')
          ->setHref('#')
          ->addClass('conpherence-participant-toggle'));
    }

    return $header;
  }

}
