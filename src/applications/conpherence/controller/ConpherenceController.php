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
      pht('New Message'),
      $this->getApplicationURI('new/'));

    $nav->addMenuItem(
      id(new PHUIListItemView())
      ->setName(pht('Add Participants'))
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setHref('#')
      ->addSigil('conpherence-widget-adder')
      ->setMetadata(array('widget' => 'widgets-people')));

    $nav->addMenuItem(
      id(new PHUIListItemView())
      ->setName(pht('New Calendar Item'))
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setHref('/calendar/event/create/')
      ->addSigil('conpherence-widget-adder')
      ->setMetadata(array('widget' => 'widgets-calendar')));

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->setBorder(true);

    $crumbs
      ->addAction(
        id(new PHUIListItemView())
        ->setName(pht('New Message'))
        ->setHref($this->getApplicationURI('new/'))
        ->setIcon('fa-plus-square')
        ->setWorkflow(true))
      ->addAction(
        id(new PHUIListItemView())
        ->setName(pht('Thread'))
        ->setHref('#')
        ->setIcon('fa-bars')
        ->setStyle('display: none;')
        ->addClass('device-widgets-selector')
        ->addSigil('device-widgets-selector'));
    return $crumbs;
  }

  protected function buildHeaderPaneContent(ConpherenceThread $conpherence) {
    $crumbs = $this->buildApplicationCrumbs();
    $title = $this->getConpherenceTitle($conpherence);
    $crumbs->addCrumb(
      id(new PHUICrumbView())
      ->setName($title)
      ->setHref($this->getApplicationURI('update/'.$conpherence->getID().'/'))
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

  protected function getConpherenceTitle(ConpherenceThread $conpherence) {
    if ($conpherence->getTitle()) {
      $title = $conpherence->getTitle();
    } else {
      $title = pht('[No Title]');
    }
    return $title;
  }

}
