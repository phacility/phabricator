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
          ->setLabel('Subject')
          ->setValue($mail->getSubject()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Created')
          ->setValue(phabricator_datetime($mail->getDateCreated(), $user)))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Status')
          ->setValue($status))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Retry Count')
          ->setValue($mail->getRetryCount()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Message')
          ->setValue($mail->getMessage()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Related PHID')
          ->setValue($mail->getRelatedPHID()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI(), 'Done'));

    $panel = new AphrontPanelView();
    $panel->setHeader('View Email');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);

    return $this->buildApplicationPage(
      $panel,
      array(
        'title' => 'View Mail',
      ));
  }

}
