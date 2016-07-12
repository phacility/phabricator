<?php

final class ReleephRequestActionController
  extends ReleephRequestController {

  public function handleRequest(AphrontRequest $request) {
    $action = $request->getURIData('action');
    $id = $request->getURIData('requestID');
    $viewer = $request->getViewer();

    $request->validateCSRF();

    $pull = id(new ReleephRequestQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$pull) {
      return new Aphront404Response();
    }

    $branch = $pull->getBranch();
    $product = $branch->getProduct();
    $origin_uri = '/'.$pull->getMonogram();

    $editor = id(new ReleephRequestTransactionalEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request);

    $xactions = array();

    switch ($action) {
      case 'want':
      case 'pass':
        static $action_map = array(
          'want' => ReleephRequest::INTENT_WANT,
          'pass' => ReleephRequest::INTENT_PASS,
        );
        $intent = $action_map[$action];
        $xactions[] = id(new ReleephRequestTransaction())
          ->setTransactionType(ReleephRequestTransaction::TYPE_USER_INTENT)
          ->setMetadataValue(
            'isAuthoritative',
            $product->isAuthoritative($viewer))
          ->setNewValue($intent);
        break;

      case 'mark-manually-picked':
      case 'mark-manually-reverted':
        if (
          $pull->getRequestUserPHID() === $viewer->getPHID() ||
          $product->isAuthoritative($viewer)) {

          // We're all good!
        } else {
          throw new Exception(
            pht(
              "Bug! Only pushers or the requestor can manually change a ".
              "request's in-branch status!"));
        }

        if ($action === 'mark-manually-picked') {
          $in_branch = 1;
          $intent = ReleephRequest::INTENT_WANT;
        } else {
          $in_branch = 0;
          $intent = ReleephRequest::INTENT_PASS;
        }

        $xactions[] = id(new ReleephRequestTransaction())
          ->setTransactionType(ReleephRequestTransaction::TYPE_USER_INTENT)
          ->setMetadataValue('isManual', true)
          ->setMetadataValue('isAuthoritative', true)
          ->setNewValue($intent);

        $xactions[] = id(new ReleephRequestTransaction())
          ->setTransactionType(ReleephRequestTransaction::TYPE_MANUAL_IN_BRANCH)
          ->setNewValue($in_branch);

        break;

      default:
        throw new Exception(
          pht('Unknown or unimplemented action %s.', $action));
    }

    $editor->applyTransactions($pull, $xactions);

    if ($request->getBool('render')) {
      $field_list = PhabricatorCustomField::getObjectFields(
        $pull,
        PhabricatorCustomField::ROLE_VIEW);

      $field_list
        ->setViewer($viewer)
        ->readFieldsFromStorage($pull);

      // TODO: This should be more modern and general.
      $engine = id(new PhabricatorMarkupEngine())
        ->setViewer($viewer);
      foreach ($field_list->getFields() as $field) {
        if ($field->shouldMarkup()) {
          $field->setMarkupEngine($engine);
        }
      }
      $engine->process();

      $pull_box = id(new ReleephRequestView())
        ->setUser($viewer)
        ->setCustomFields($field_list)
        ->setPullRequest($pull)
        ->setIsListView(true);

      return id(new AphrontAjaxResponse())->setContent(
        array(
          'markup' => hsprintf('%s', $pull_box),
        ));
    }

    return id(new AphrontRedirectResponse())->setURI($origin_uri);
  }
}
