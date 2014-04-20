<?php

final class ConduitAPI_releephwork_getcommitmessage_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return
      "Get commit message components for building ".
      "a ReleephRequest commit message.";
  }

  public function defineParamTypes() {
    return array(
      'requestPHID' => 'required string',
      'action'      => 'required enum<"pick", "revert">',
    );
  }

  public function defineReturnType() {
    return 'dict<string, string>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $releeph_request = id(new ReleephRequestQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($request->getValue('requestPHID')))
      ->executeOne();

    $action = $request->getValue('action');

    $title = $releeph_request->getSummaryForDisplay();

    $commit_message = array();

    $branch = $releeph_request->getBranch();
    $project = $branch->getProduct();

    $selector = $project->getReleephFieldSelector();
    $fields = $selector->getFieldSpecifications();
    $fields = $selector->sortFieldsForCommitMessage($fields);

    foreach ($fields as $field) {
      $field
        ->setUser($request->getUser())
        ->setReleephProject($project)
        ->setReleephBranch($branch)
        ->setReleephRequest($releeph_request);

      $label = null;
      $value = null;

      switch ($action) {
        case 'pick':
          if ($field->shouldAppearOnCommitMessage()) {
            $label = $field->renderLabelForCommitMessage();
            $value = $field->renderValueForCommitMessage();
          }
          break;

        case 'revert':
          if ($field->shouldAppearOnRevertMessage()) {
            $label = $field->renderLabelForRevertMessage();
            $value = $field->renderValueForRevertMessage();
          }
          break;
      }

      if ($label && $value) {
        if (strpos($value, "\n") !== false ||
            substr($value, 0, 2) === '  ') {
          $commit_message[] = "{$label}:\n{$value}";
        } else {
          $commit_message[] = "{$label}: {$value}";
        }
      }
    }

    return array(
      'title' => $title,
      'body'  => implode("\n\n", $commit_message),
    );
  }

}
