<?php

final class PhabricatorMetaMTAViewController
  extends PhabricatorMetaMTAController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $mail = id(new PhabricatorMetaMTAMail())->load($this->id);
    if (!$mail) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Mail %d', $mail->getID())));

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Mail: %s', $mail->getSubject()));

    $properties = $this->buildPropertyListView($mail);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $properties,
      ),
      array(
        'title' => pht('View Mail'),
        'device' => true,
        'dust' => true,
      ));
  }

  private function buildPropertyListView(PhabricatorMetaMTAMail $mail) {
    $viewer = $this->getRequest()->getUser();

    $related_phid = $mail->getRelatedPHID();

    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Status'),
      PhabricatorMetaMTAMail::getReadableStatus($mail->getStatus()));

    $view->addProperty(
      pht('Retry Count'),
      $mail->getRetryCount());

    $view->addProperty(
      pht('Delivery Message'),
      nonempty($mail->getMessage(), '-'));

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($mail->getDateCreated(), $viewer));

    $phids = array();
    $phids[] = $related_phid;
    $handles = $this->loadViewerHandles($phids);

    if ($related_phid) {
      $related_object = $handles[$related_phid]->renderLink();
    } else {
      $related_object = phutil_tag('em', array(), pht('None'));
    }

    $view->addProperty(pht('Related Object'), $related_object);

    return $view;
  }
}
