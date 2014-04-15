<?php

final class ReleephRequestActionController
  extends ReleephRequestController {

  private $action;
  private $requestID;

  public function willProcessRequest(array $data) {
    $this->action = $data['action'];
    $this->requestID = $data['requestID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $pull = id(new ReleephRequestQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->requestID))
      ->executeOne();
    if (!$pull) {
      return new Aphront404Response();
    }

    $branch = $pull->getBranch();
    $product = $branch->getProduct();

    $branch->populateReleephRequestHandles($viewer, array($pull));

    $action = $this->action;

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
          'pass' => ReleephRequest::INTENT_PASS);
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
            "Bug!  Only pushers or the requestor can manually change a ".
            "request's in-branch status!");
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
        throw new Exception("unknown or unimplemented action {$action}");
    }

    $editor->applyTransactions($pull, $xactions);

    // If we're adding a new user to userIntents, we'll have to re-populate
    // request handles to load that user's data.
    //
    // This is cheap enough to do every time.
    $branch->populateReleephRequestHandles($viewer, array($pull));

    $list = id(new ReleephRequestHeaderListView())
      ->setReleephProject($product)
      ->setReleephBranch($branch)
      ->setReleephRequests(array($pull))
      ->setUser($viewer)
      ->setAphrontRequest($request);

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'markup' => hsprintf('%s', $list->renderInner()),
      ));
  }
}
