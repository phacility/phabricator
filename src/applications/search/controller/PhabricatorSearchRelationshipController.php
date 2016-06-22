<?php

final class PhabricatorSearchRelationshipController
  extends PhabricatorSearchBaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $phid = $request->getURIData('sourcePHID');
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $list = PhabricatorObjectRelationshipList::newForObject(
      $viewer,
      $object);

    $relationship_key = $request->getURIData('relationshipKey');
    $relationship = $list->getRelationship($relationship_key);
    if (!$relationship) {
      return new Aphront404Response();
    }

    $src_phid = $object->getPHID();
    $edge_type = $relationship->getEdgeConstant();

    $dst_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $src_phid,
      $edge_type);

    $all_phids = $dst_phids;
    $all_phids[] = $src_phid;

    $handles = $viewer->loadHandles($all_phids);
    $src_handle = $handles[$src_phid];

    $done_uri = $src_handle->getURI();

    if ($request->isFormPost()) {
      $phids = explode(';', $request->getStr('phids'));
      $phids = array_filter($phids);
      $phids = array_values($phids);

      // TODO: Embed these in the form instead, to gracefully resolve
      // concurrent edits like we do for subscribers and projects.
      $old_phids = $dst_phids;

      $add_phids = $phids;
      $rem_phids = array_diff($old_phids, $add_phids);

      if ($add_phids) {
        $dst_objects = id(new PhabricatorObjectQuery())
          ->setViewer($viewer)
          ->withPHIDs($phids)
          ->setRaisePolicyExceptions(true)
          ->execute();
        $dst_objects = mpull($dst_objects, null, 'getPHID');
      } else {
        $dst_objects = array();
      }

      try {
        foreach ($add_phids as $add_phid) {
          $dst_object = idx($dst_objects, $add_phid);
          if (!$dst_object) {
            throw new Exception(
              pht(
                'You can not create a relationship to object "%s" because '.
                'the object does not exist or could not be loaded.',
                $add_phid));
          }

          if (!$relationship->canRelateObjects($object, $dst_object)) {
            throw new Exception(
              pht(
                'You can not create a relationship (of type "%s") to object '.
                '"%s" because it is not the right type of object for this '.
                'relationship.',
                $relationship->getRelationshipConstant(),
                $add_phid));
          }
        }
      } catch (Exception $ex) {
        return $this->newUnrelatableObjectResponse($ex, $done_uri);
      }

      $editor = $object->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $xactions = array();
      $xactions[] = $object->getApplicationTransactionTemplate()
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_type)
        ->setNewValue(array(
          '+' => array_fuse($add_phids),
          '-' => array_fuse($rem_phids),
        ));

      try {
        $editor->applyTransactions($object, $xactions);

        return id(new AphrontRedirectResponse())->setURI($done_uri);
      } catch (PhabricatorEdgeCycleException $ex) {
        return $this->newGraphCycleResponse($ex, $done_uri);
      }
    }

    $handles = iterator_to_array($handles);
    $handles = array_select_keys($handles, $dst_phids);

    // TODO: These are hard-coded for now.
    $filters = array(
      'assigned' => pht('Assigned to Me'),
      'created' => pht('Created By Me'),
      'open' => pht('All Open Objects'),
      'all' => pht('All Objects'),
    );

    $dialog_title = $relationship->getDialogTitleText();
    $dialog_header = $relationship->getDialogHeaderText();
    $dialog_button = $relationship->getDialogButtonText();
    $dialog_instructions = $relationship->getDialogInstructionsText();

    // TODO: Remove this, this is just legacy support.
    $legacy_kinds = array(
      ManiphestTaskHasCommitEdgeType::EDGECONST => 'CMIT',
      ManiphestTaskHasMockEdgeType::EDGECONST => 'MOCK',
      ManiphestTaskHasRevisionEdgeType::EDGECONST => 'DREV',
    );

    $edge_type = $relationship->getEdgeConstant();
    $legacy_kind = idx($legacy_kinds, $edge_type);
    if (!$legacy_kind) {
      throw new Exception(
        pht('Only specific legacy relationships are supported!'));
    }

    return id(new PhabricatorObjectSelectorDialog())
      ->setUser($viewer)
      ->setHandles($handles)
      ->setFilters($filters)
      ->setSelectedFilter('created')
      ->setExcluded($phid)
      ->setCancelURI($done_uri)
      ->setSearchURI("/search/select/{$legacy_kind}/edge/")
      ->setTitle($dialog_title)
      ->setHeader($dialog_header)
      ->setButtonText($dialog_button)
      ->setInstructions($dialog_instructions)
      ->buildDialog();
  }

  private function newGraphCycleResponse(
    PhabricatorEdgeCycleException $ex,
    $done_uri) {

    $viewer = $this->getViewer();
    $cycle = $ex->getCycle();

    $handles = $this->loadViewerHandles($cycle);
    $names = array();
    foreach ($cycle as $cycle_phid) {
      $names[] = $handles[$cycle_phid]->getFullName();
    }

    $message = pht(
      'You can not create that relationship because it would create a '.
      'circular dependency: %s.',
      implode(" \xE2\x86\x92 ", $names));

    return $this->newDialog()
      ->setTitle(pht('Circular Dependency'))
      ->appendParagraph($message)
      ->addCancelButton($done_uri);
  }

  private function newUnrelatableObjectResponse(Exception $ex, $done_uri) {
    $message = $ex->getMessage();

    return $this->newDialog()
      ->setTitle(pht('Invalid Relationship'))
      ->appendParagraph($message)
      ->addCancelButton($done_uri);
  }

}
