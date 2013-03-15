<?php

final class ReleephRequestActionController extends ReleephController {

  private $action;

  public function willProcessRequest(array $data) {
    parent::willProcessRequest($data);
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $releeph_branch  = $this->getReleephBranch();
    $releeph_request = $this->getReleephRequest();

    $releeph_branch->populateReleephRequestHandles(
      $request->getUser(), array($releeph_request));

    $action = $this->action;

    $user = $request->getUser();

    $origin_uri = $releeph_request->loadReleephBranch()->getURI();

    $editor = id(new ReleephRequestEditor($releeph_request))
      ->setActor($user);

    switch ($action) {
      case 'want':
      case 'pass':
        static $action_map = array(
          'want' => ReleephRequest::INTENT_WANT,
          'pass' => ReleephRequest::INTENT_PASS);
        $intent = $action_map[$action];
        $editor->changeUserIntent($user, $intent);
        break;

      case 'mark-manually-picked':
        $editor->markManuallyActioned('pick');
        break;

      case 'mark-manually-reverted':
        $editor->markManuallyActioned('revert');
        break;

      default:
        throw new Exception("unknown or unimplemented action {$action}");
    }

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
