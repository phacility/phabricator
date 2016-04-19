<?php

final class PhabricatorBadgesAwardController
  extends PhabricatorBadgesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $view_uri = '/p/'.$user->getUsername();

    if ($request->isFormPost()) {
      $badge_phids = $request->getArr('badgePHIDs');
      $badges = id(new PhabricatorBadgesQuery())
        ->setViewer($viewer)
        ->withPHIDs($badge_phids)
        ->needRecipients(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
            PhabricatorPolicyCapability::CAN_VIEW,
          ))
        ->execute();
      if (!$badges) {
        return new Aphront404Response();
      }
      $award_phids = array($user->getPHID());

      foreach ($badges as $badge) {
        $xactions = array();
        $xactions[] = id(new PhabricatorBadgesTransaction())
          ->setTransactionType(PhabricatorBadgesTransaction::TYPE_AWARD)
          ->setNewValue($award_phids);

        $editor = id(new PhabricatorBadgesEditor($badge))
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
          ->applyTransactions($badge, $xactions);
      }

      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Badge'))
          ->setName('badgePHIDs')
          ->setDatasource(
            id(new PhabricatorBadgesDatasource())
              ->setParameters(
                array(
                  'recipientPHID' => $user->getPHID(),
                  ))));

    $dialog = $this->newDialog()
      ->setTitle(pht('Grant Badge'))
      ->appendForm($form)
      ->addCancelButton($view_uri)
      ->addSubmitButton(pht('Award'));

    return $dialog;
  }

}
