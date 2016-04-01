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

    $awards = $badge->getAwards();
    $recipient_phids = mpull($awards, 'getRecipientPHID');
    $remove_phid = $request->getStr('phid');

    if (!in_array($remove_phid, $recipient_phids)) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('view/'.$badge->getID().'/');

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType(PhabricatorBadgesTransaction::TYPE_REVOKE)
        ->setNewValue(array($remove_phid));

      $editor = id(new PhabricatorBadgesEditor($badge))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($badge, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
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
      ->addCancelButton($view_uri)
      ->addSubmitButton(pht('Revoke Badge'));

    return $dialog;
  }

}
