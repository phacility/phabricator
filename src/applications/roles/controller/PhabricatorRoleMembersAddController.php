<?php

final class PhabricatorRoleMembersAddController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $this->setRole($role);
    $done_uri = "/role/members/{$id}/";

    if (!$role->supportsEditMembers()) {
      $copy = pht('Parent roles and milestones do not support adding '.
        'members. You can add members directly to any non-parent subrole.');

      return $this->newDialog()
        ->setTitle(pht('Unsupported Role'))
        ->appendParagraph($copy)
        ->addCancelButton($done_uri);
    }

    if ($request->isFormPost()) {
      $member_phids = $request->getArr('memberPHIDs');

      $type_member = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;

      $xactions = array();

      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_member)
        ->setNewValue(
          array(
            '+' => array_fuse($member_phids),
          ));

      $editor = id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($done_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('memberPHIDs')
          ->setLabel(pht('Members'))
          ->setDatasource(new PhabricatorPeopleDatasource()));

    return $this->newDialog()
      ->setTitle(pht('Add Members'))
      ->appendForm($form)
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Add Members'));
  }

}
