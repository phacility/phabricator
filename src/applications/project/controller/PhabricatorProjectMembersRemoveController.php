<?php

final class PhabricatorProjectMembersRemoveController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needMembers(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $member_phids = $project->getMemberPHIDs();
    $remove_phid = $request->getStr('phid');

    if (!in_array($remove_phid, $member_phids)) {
      return new Aphront404Response();
    }

    $members_uri = $this->getApplicationURI('members/'.$project->getID().'/');

    if ($request->isFormPost()) {
      $member_spec = array();
      $member_spec['-'] = array($remove_phid => $remove_phid);

      $type_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_member)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($members_uri);
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($remove_phid))
      ->executeOne();

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Really Remove Member?'))
      ->appendParagraph(
        pht(
          'Really remove %s from the project %s?',
          phutil_tag('strong', array(), $handle->getName()),
          phutil_tag('strong', array(), $project->getName())))
      ->addCancelButton($members_uri)
      ->addSubmitButton(pht('Remove Project Member'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
