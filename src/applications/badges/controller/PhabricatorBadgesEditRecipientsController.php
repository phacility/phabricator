<?php

final class PhabricatorBadgesEditRecipientsController
  extends PhabricatorBadgesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $badge = id(new PhabricatorBadgesQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needRecipients(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->executeOne();
    if (!$badge) {
      return new Aphront404Response();
    }

    $recipient_phids = $badge->getRecipientPHIDs();
    $view_uri = $this->getApplicationURI('view/'.$badge->getID().'/');

    if ($request->isFormPost()) {
      $recipient_spec = array();

      $remove = $request->getStr('remove');
      if ($remove) {
        $recipient_spec['-'] = array_fuse(array($remove));
      }

      $add_recipients = $request->getArr('phids');
      if ($add_recipients) {
        $recipient_spec['+'] = array_fuse($add_recipients);
      }

      $type_recipient = PhabricatorBadgeHasRecipientEdgeType::EDGECONST;

      $xactions = array();

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_recipient)
        ->setNewValue($recipient_spec);

      $editor = id(new PhabricatorBadgesEditor($badge))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($badge, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }

    $recipient_phids = array_reverse($recipient_phids);
    $handles = $this->loadViewerHandles($recipient_phids);

    $state = array();
    foreach ($handles as $handle) {
      $state[] = array(
        'phid' => $handle->getPHID(),
        'name' => $handle->getFullName(),
      );
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $badge,
      PhabricatorPolicyCapability::CAN_EDIT);

    $form_box = null;
    $title = pht('Add Recipient');
    if ($can_edit) {
      $header_name = pht('Edit Recipients');

      $form = new AphrontFormView();
      $form
        ->setUser($viewer)
        ->setFullWidth(true)
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setName('phids')
            ->setLabel(pht('Add Recipients'))
            ->setDatasource(new PhabricatorPeopleDatasource()));
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Award Badges'))
      ->appendForm($form)
      ->addCancelButton($view_uri)
      ->addSubmitButton(pht('Add Recipients'));

    return $dialog;
  }

}
