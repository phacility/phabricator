<?php

final class PhabricatorDaemonTimelineEventController
  extends PhabricatorDaemonController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $event = id(new PhabricatorTimelineEvent('NULL'))->load($this->id);
    if (!$event) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($event->getDataID()) {
      $data = id(new PhabricatorTimelineEventData())->load(
        $event->getDataID());
    }

    if ($data) {
      $data = json_encode($data->getEventData());
    } else {
      $data = 'null';
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('ID')
          ->setValue($event->getID()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Type')
          ->setValue($event->getType()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setDisabled(true)
          ->setLabel('Data')
          ->setValue($data))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/daemon/timeline/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Event');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('timeline');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Timeline Event',
      ));
  }

}
