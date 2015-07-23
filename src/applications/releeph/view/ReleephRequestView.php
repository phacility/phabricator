<?php

final class ReleephRequestView extends AphrontView {

  private $pullRequest;
  private $customFields;
  private $isListView;

  public function setIsListView($is_list_view) {
    $this->isListView = $is_list_view;
    return $this;
  }

  public function getIsListView() {
    return $this->isListView;
  }

  public function setCustomFields(PhabricatorCustomFieldList $custom_fields) {
    $this->customFields = $custom_fields;
    return $this;
  }

  public function getCustomFields() {
    return $this->customFields;
  }

  public function setPullRequest(ReleephRequest $pull_request) {
    $this->pullRequest = $pull_request;
    return $this;
  }

  public function getPullRequest() {
    return $this->pullRequest;
  }

  public function render() {
    $viewer = $this->getUser();

    $field_list = $this->getCustomFields();
    $pull = $this->getPullRequest();

    $header = $this->buildHeader($pull);

    $action_list = $this->buildActionList($pull);

    $property_list = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($action_list);

    $field_list->appendFieldsToPropertyList(
      $pull,
      $viewer,
      $property_list);

    $warnings = $this->getWarnings($pull);

    if ($this->getIsListView()) {
      Javelin::initBehavior('releeph-request-state-change');
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setFormErrors($warnings)
      ->addSigil('releeph-request-box')
      ->setMetadata(array('uri' => '/'.$pull->getMonogram()))
      ->appendChild($property_list);
  }

  private function buildHeader(ReleephRequest $pull) {
    $header_text = $pull->getSummaryForDisplay();
    if ($this->getIsListView()) {
      $header_text = phutil_tag(
        'a',
        array(
          'href' => '/'.$pull->getMonogram(),
        ),
        $header_text);
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text)
      ->setUser($this->getUser())
      ->setPolicyObject($pull);

    switch ($pull->getStatus()) {
      case ReleephRequestStatus::STATUS_REQUESTED:
        $icon = 'open';
        $color = null;
        break;
      case ReleephRequestStatus::STATUS_REJECTED:
        $icon = 'reject';
        $color = 'red';
        break;
      case ReleephRequestStatus::STATUS_PICKED:
        $icon = 'accept';
        $color = 'green';
        break;
      case ReleephRequestStatus::STATUS_REVERTED:
      case ReleephRequestStatus::STATUS_ABANDONED:
        $icon = 'reject';
        $color = 'dark';
        break;
      case ReleephRequestStatus::STATUS_NEEDS_PICK:
        $icon = 'warning';
        $color = 'green';
        break;
      case ReleephRequestStatus::STATUS_NEEDS_REVERT:
        $icon = 'warning';
        $color = 'red';
        break;
      default:
        $icon = 'question';
        $color = null;
        break;
    }
    $text = ReleephRequestStatus::getStatusDescriptionFor($pull->getStatus());
    $header->setStatus($icon, $color, $text);

    return $header;
  }

  private function buildActionList(ReleephRequest $pull) {
    $viewer = $this->getUser();
    $id = $pull->getID();

    $edit_uri = '/releeph/request/edit/'.$id.'/';

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $product = $pull->getBranch()->getProduct();
    $viewer_is_pusher = $product->isAuthoritativePHID($viewer->getPHID());
    $viewer_is_requestor = ($pull->getRequestUserPHID() == $viewer->getPHID());

    if ($viewer_is_pusher) {
      $yes_text = pht('Approve Pull');
      $no_text = pht('Reject Pull');
      $yes_icon = 'fa-check';
      $no_icon = 'fa-times';
    } else if ($viewer_is_requestor) {
      $yes_text = pht('Request Pull');
      $no_text = pht('Cancel Pull');
      $yes_icon = 'fa-check';
      $no_icon = 'fa-times';
    } else {
      $yes_text = pht('Support Pull');
      $no_text = pht('Discourage Pull');
      $yes_icon = 'fa-thumbs-o-up';
      $no_icon = 'fa-thumbs-o-down';
    }

    $yes_href = '/releeph/request/action/want/'.$id.'/';
    $no_href = '/releeph/request/action/pass/'.$id.'/';

    $intents = $pull->getUserIntents();
    $current_intent = idx($intents, $viewer->getPHID());

    $yes_disabled = ($current_intent == ReleephRequest::INTENT_WANT);
    $no_disabled = ($current_intent == ReleephRequest::INTENT_PASS);

    $use_workflow = (!$this->getIsListView());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($yes_text)
        ->setHref($yes_href)
        ->setWorkflow($use_workflow)
        ->setRenderAsForm($use_workflow)
        ->setDisabled($yes_disabled)
        ->addSigil('releeph-request-state-change')
        ->addSigil('want')
        ->setIcon($yes_icon));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($no_text)
        ->setHref($no_href)
        ->setWorkflow($use_workflow)
        ->setRenderAsForm($use_workflow)
        ->setDisabled($no_disabled)
        ->addSigil('releeph-request-state-change')
        ->addSigil('pass')
        ->setIcon($no_icon));


    if ($viewer_is_pusher || $viewer_is_requestor) {

      $pulled_href = '/releeph/request/action/mark-manually-picked/'.$id.'/';
      $revert_href = '/releeph/request/action/mark-manually-reverted/'.$id.'/';

      if ($pull->getInBranch()) {
        $view->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Mark as Reverted'))
            ->setHref($revert_href)
            ->setWorkflow($use_workflow)
            ->setRenderAsForm($use_workflow)
            ->addSigil('releeph-request-state-change')
            ->addSigil('mark-manually-reverted')
            ->setIcon($no_icon));
      } else {
        $view->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Mark as Pulled'))
            ->setHref($pulled_href)
            ->setWorkflow($use_workflow)
            ->setRenderAsForm($use_workflow)
            ->addSigil('releeph-request-state-change')
            ->addSigil('mark-manually-picked')
            ->setIcon('fa-exclamation-triangle'));
      }
    }


    if (!$this->getIsListView()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Pull Request'))
          ->setIcon('fa-pencil')
          ->setHref($edit_uri));
    }

    return $view;
  }

  private function getWarnings(ReleephRequest $pull) {
    $warnings = array();

    switch ($pull->getStatus()) {
      case ReleephRequestStatus::STATUS_NEEDS_PICK:
        if ($pull->getPickStatus() == ReleephRequest::PICK_FAILED) {
          $warnings[] = pht('Last pull failed!');
        }
        break;
      case ReleephRequestStatus::STATUS_NEEDS_REVERT:
        if ($pull->getPickStatus() == ReleephRequest::REVERT_FAILED) {
          $warnings[] = pht('Last revert failed!');
        }
        break;
    }

    return $warnings;
  }

}
