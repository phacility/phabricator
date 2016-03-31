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
      $xactions = array();
      $badge_phid = $request->getStr('badgePHID');
      $badge = id(new PhabricatorBadgesQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($badge_phid))
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
      $award_phids = array($user->getPHID());

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType(PhabricatorBadgesTransaction::TYPE_AWARD)
        ->setNewValue($award_phids);

      $editor = id(new PhabricatorBadgesEditor($badge))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($badge, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }

    $badges = id(new PhabricatorBadgesQuery())
      ->setViewer($viewer)
      ->withStatuses(array(
        PhabricatorBadgesBadge::STATUS_ACTIVE,
      ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $options = mpull($badges, 'getName', 'getPHID');
    asort($options);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Badge'))
          ->setName('badgePHID')
          ->setOptions($options));

    $dialog = $this->newDialog()
      ->setTitle(pht('Grant Badge'))
      ->appendForm($form)
      ->addCancelButton($view_uri)
      ->addSubmitButton(pht('Award'));

    return $dialog;
  }

}
