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

    $status = PhabricatorMetaMTAMail::getReadableStatus($mail->getStatus());

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    $form
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Subject'))
          ->setValue($mail->getSubject()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Created'))
          ->setValue(phabricator_datetime($mail->getDateCreated(), $user)))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Status'))
          ->setValue($status))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Retry Count'))
          ->setValue($mail->getRetryCount()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Message'))
          ->setValue($mail->getMessage()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Related PHID'))
          ->setValue($mail->getRelatedPHID()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI(), pht('Done')));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('View Email'));
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->setNoBackground();

    return $this->buildApplicationPage(
      $panel,
      array(
        'title' => pht('View Mail'),
        'device' => true,
      ));
  }

}
