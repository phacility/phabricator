<?php

/**
 * @group maniphest
 */
final class ManiphestTaskDescriptionPreviewController
  extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $description = $request->getStr('description');

    $task = new ManiphestTask();
    $task->setDescription($description);

    $output = PhabricatorMarkupEngine::renderOneObject(
      $task,
      ManiphestTask::MARKUP_FIELD_DESCRIPTION,
      $request->getUser());

    $content =
      '<div class="phabricator-remarkup">'.
        $output.
      '</div>';

    return id(new AphrontAjaxResponse())
      ->setContent($content);
  }
}
