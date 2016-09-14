<?php

final class PhabricatorSearchRelationshipController
  extends PhabricatorSearchBaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $object = $this->loadRelationshipObject();
    if (!$object) {
      return new Aphront404Response();
    }

    $relationship = $this->loadRelationship($object);
    if (!$relationship) {
      return new Aphront404Response();
    }

    $src_phid = $object->getPHID();
    $edge_type = $relationship->getEdgeConstant();

    // If this is a normal relationship, users can remove related objects. If
    // it's a special relationship like a merge, we can't undo it, so we won't
    // prefill the current related objects.
    if ($relationship->canUndoRelationship()) {
      $dst_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $src_phid,
        $edge_type);
    } else {
      $dst_phids = array();
    }

    $all_phids = $dst_phids;
    $all_phids[] = $src_phid;

    $handles = $viewer->loadHandles($all_phids);
    $src_handle = $handles[$src_phid];

    $done_uri = $src_handle->getURI();
    $initial_phids = $dst_phids;

    $maximum = $relationship->getMaximumSelectionSize();

    if ($request->isFormPost()) {
      $phids = explode(';', $request->getStr('phids'));
      $phids = array_filter($phids);
      $phids = array_values($phids);

      // The UI normally enforces this with Javascript, so this is just a
      // sanity check and does not need to be particularly user-friendly.
      if ($maximum && (count($phids) > $maximum)) {
        throw new Exception(
          pht(
            'Too many relationships (%s, of type "%s").',
            phutil_count($phids),
            $relationship->getRelationshipConstant()));
      }

      $initial_phids = $request->getStrList('initialPHIDs');

      // Apply the changes as adds and removes relative to the original state
      // of the object when the dialog was rendered so that two users adding
      // relationships at the same time don't race and overwrite one another.
      $add_phids = array_diff($phids, $initial_phids);
      $rem_phids = array_diff($initial_phids, $phids);
      $all_phids = array_merge($add_phids, $rem_phids);

      $capabilities = $relationship->getRequiredRelationshipCapabilities();

      if ($all_phids) {
        $dst_objects = id(new PhabricatorObjectQuery())
          ->setViewer($viewer)
          ->withPHIDs($all_phids)
          ->setRaisePolicyExceptions(true)
          ->requireCapabilities($capabilities)
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

          if ($add_phid == $src_phid) {
            throw new Exception(
              pht(
                'You can not create a relationship to object "%s" because '.
                'objects can not be related to themselves.',
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

      $content_source = PhabricatorContentSource::newFromRequest($request);
      $relationship->setContentSource($content_source);

      $editor = $object->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSource($content_source)
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

      $add_objects = array_select_keys($dst_objects, $add_phids);
      $rem_objects = array_select_keys($dst_objects, $rem_phids);

      if ($add_objects || $rem_objects) {
        $more_xactions = $relationship->willUpdateRelationships(
          $object,
          $add_objects,
          $rem_objects);
        foreach ($more_xactions as $xaction) {
          $xactions[] = $xaction;
        }
      }

      try {
        $editor->applyTransactions($object, $xactions);

        if ($add_objects || $rem_objects) {
          $relationship->didUpdateRelationships(
            $object,
            $add_objects,
            $rem_objects);
        }

        return id(new AphrontRedirectResponse())->setURI($done_uri);
      } catch (PhabricatorEdgeCycleException $ex) {
        return $this->newGraphCycleResponse($ex, $done_uri);
      }
    }

    $handles = iterator_to_array($handles);
    $handles = array_select_keys($handles, $dst_phids);

    $dialog_title = $relationship->getDialogTitleText();
    $dialog_header = $relationship->getDialogHeaderText();
    $dialog_button = $relationship->getDialogButtonText();
    $dialog_instructions = $relationship->getDialogInstructionsText();

    $source_uri = $relationship->getSourceURI($object);

    $source = $relationship->newSource();

    $filters = $source->getFilters();
    $selected_filter = $source->getSelectedFilter();

    return id(new PhabricatorObjectSelectorDialog())
      ->setUser($viewer)
      ->setInitialPHIDs($initial_phids)
      ->setHandles($handles)
      ->setFilters($filters)
      ->setSelectedFilter($selected_filter)
      ->setExcluded($src_phid)
      ->setCancelURI($done_uri)
      ->setSearchURI($source_uri)
      ->setTitle($dialog_title)
      ->setHeader($dialog_header)
      ->setButtonText($dialog_button)
      ->setInstructions($dialog_instructions)
      ->setMaximumSelectionSize($maximum)
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
      'circular dependency:');

    $list = implode(" \xE2\x86\x92 ", $names);

    return $this->newDialog()
      ->setTitle(pht('Circular Dependency'))
      ->appendParagraph($message)
      ->appendParagraph($list)
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
