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
    $releeph_request = id(new ReleephRequest())
      ->loadOneWhere('phid = %s', $request->getValue('requestPHID'));

    $action = $request->getValue('action');

    $title = $releeph_request->getSummaryForDisplay();

    $commit_message = array();

    $project = $releeph_request->loadReleephProject();
    $branch = $releeph_request->loadReleephBranch();

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
