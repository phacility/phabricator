<?php

final class PhabricatorBadgesRemoveRecipientsController
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
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$badge) {
      return new Aphront404Response();
    }

    $recipient_phids = $badge->getRecipientPHIDs();
    $remove_phid = $request->getStr('phid');

    if (!in_array($remove_phid, $recipient_phids)) {
      return new Aphront404Response();
    }

    $recipients_uri =
      $this->getApplicationURI('recipients/'.$badge->getID().'/');

    if ($request->isFormPost()) {
      $recipient_spec = array();
      $recipient_spec['-'] = array($remove_phid => $remove_phid);

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
        ->setURI($recipients_uri);
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($remove_phid))
      ->executeOne();

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Really Revoke Badge?'))
      ->appendParagraph(
        pht(
          'Really revoke the badge "%s" from %s?',
          phutil_tag('strong', array(), $badge->getName()),
          phutil_tag('strong', array(), $handle->getName())))
      ->addCancelButton($recipients_uri)
      ->addSubmitButton(pht('Revoke Badge'));

    return $dialog;
  }

}
