<?php

final class ReleephRequestHeaderListView
  extends AphrontView {

  private $originType;
  private $releephProject;
  private $releephBranch;
  private $releephRequests;
  private $aphrontRequest;
  private $reload = false;

  private $errors = array();

  public function setOriginType($origin) {
    $this->originType = $origin;
    return $this;
  }

  public function setReleephProject(ReleephProject $rp) {
    $this->releephProject = $rp;
    return $this;
  }

  public function setReleephBranch(ReleephBranch $rb) {
    $this->releephBranch = $rb;
    return $this;
  }

  public function setReleephRequests(array $requests) {
    assert_instances_of($requests, 'ReleephRequest');
    $this->releephRequests = $requests;
    return $this;
  }

  public function setAphrontRequest(AphrontRequest $request) {
    $this->aphrontRequest = $request;
    return $this;
  }

  public function setReloadOnStateChange($bool) {
    $this->reload = $bool;
    return $this;
  }

  public function render() {
    $views = $this->renderInner();
    require_celerity_resource('phabricator-notification-css');
    Javelin::initBehavior('releeph-request-state-change', array(
      'reload' => $this->reload,
    ));

    $error_view = null;
    if ($this->errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Bulk load errors')
        ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
        ->setErrors($this->errors)
        ->render();
    }

    $list = phutil_tag(
      'div',
      array(
        'data-sigil' => 'releeph-request-header-list',
      ),
      $views);

    return $this->renderSingleView(array(
      $error_view,
      $list));
  }

  /**
   * Required for generating markup for ReleephRequestActionController.
   *
   * That controller just needs the markup, and doesn't need to start the
   * javelin behavior.
   */
  public function renderInner() {
    $selector = $this->releephProject->getReleephFieldSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $field) {
      $field
        ->setReleephProject($this->releephProject)
        ->setReleephBranch($this->releephBranch)
        ->setUser($this->user);
      try {
        $field->bulkLoad($this->releephRequests);
      } catch (Exception $ex) {
        $this->errors[] = $ex;
      }
    }

    $field_groups = $selector->arrangeFieldsForHeaderView($fields);

    $views = array();
    foreach ($this->releephRequests as $releeph_request) {
      $views[] = id(new ReleephRequestHeaderView())
        ->setUser($this->user)
        ->setAphrontRequest($this->aphrontRequest)
        ->setOriginType($this->originType)
        ->setReleephProject($this->releephProject)
        ->setReleephBranch($this->releephBranch)
        ->setReleephRequest($releeph_request)
        ->setReleephFieldGroups($field_groups)
        ->render();
    }

    return $views;
  }

}
