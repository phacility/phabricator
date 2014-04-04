<?php

final class ReleephRequestActionController extends ReleephProjectController {

  private $action;

  public function willProcessRequest(array $data) {
    parent::willProcessRequest($data);
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $releeph_project = $this->getReleephProject();
    $releeph_branch  = $this->getReleephBranch();
    $releeph_request = $this->getReleephRequest();

    $releeph_branch->populateReleephRequestHandles(
      $request->getUser(), array($releeph_request));

    $action = $this->action;

    $user = $request->getUser();

    $origin_uri = $releeph_request->loadReleephBranch()->getURI();

    $editor = id(new ReleephRequestTransactionalEditor())
      ->setActor($user)
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
            $releeph_project->isAuthoritative($user))
          ->setNewValue($intent);
        break;

      case 'mark-manually-picked':
      case 'mark-manually-reverted':
        if (
          $releeph_request->getRequestUserPHID() === $user->getPHID() ||
          $releeph_project->isAuthoritative($user)) {

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

    $editor->applyTransactions($releeph_request, $xactions);

    // If we're adding a new user to userIntents, we'll have to re-populate
    // request handles to load that user's data.
    //
    // This is cheap enough to do every time.
    $this->getReleephBranch()->populateReleephRequestHandles(
      $user, array($releeph_request));

    $list = id(new ReleephRequestHeaderListView())
      ->setReleephProject($this->getReleephProject())
      ->setReleephBranch($this->getReleephBranch())
      ->setReleephRequests(array($releeph_request))
      ->setUser($request->getUser())
      ->setAphrontRequest($this->getRequest())
      ->setOriginType('request');

    return id(new AphrontAjaxResponse())->setContent(array(
      'markup' => head($list->renderInner())
    ));
  }
}
