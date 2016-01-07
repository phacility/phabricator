<?php

final class HarbormasterSendMessageConduitAPIMethod
  extends HarbormasterConduitAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.sendmessage';
  }

  public function getMethodSummary() {
    return pht(
      'Send a message about the status of a build target to Harbormaster, '.
      'notifying the application of build results in an external system.');
  }

  public function getMethodDescription() {
    $messages = HarbormasterMessageType::getAllMessages();

    $head_type = pht('Constant');
    $head_desc = pht('Description');
    $head_key = pht('Key');
    $head_type = pht('Type');
    $head_name = pht('Name');

    $rows = array();
    $rows[] = "| {$head_type} | {$head_desc} |";
    $rows[] = '|--------------|--------------|';
    foreach ($messages as $message) {
      $description = HarbormasterMessageType::getMessageDescription($message);
      $rows[] = "| `{$message}` | {$description} |";
    }
    $message_table = implode("\n", $rows);

    $rows = array();
    $rows[] = "| {$head_key} | {$head_type} | {$head_desc} |";
    $rows[] = '|-------------|--------------|--------------|';
    $unit_spec = HarbormasterBuildUnitMessage::getParameterSpec();
    foreach ($unit_spec as $key => $parameter) {
      $type = idx($parameter, 'type');
      $type = str_replace('|', ' '.pht('or').' ', $type);
      $description = idx($parameter, 'description');
      $rows[] = "| `{$key}` | //{$type}// | {$description} |";
    }
    $unit_table = implode("\n", $rows);

    $rows = array();
    $rows[] = "| {$head_key} | {$head_name} | {$head_desc} |";
    $rows[] = '|-------------|--------------|--------------|';
    $results = ArcanistUnitTestResult::getAllResultCodes();
    foreach ($results as $result_code) {
      $name = ArcanistUnitTestResult::getResultCodeName($result_code);
      $description = ArcanistUnitTestResult::getResultCodeDescription(
        $result_code);
      $rows[] = "| `{$result_code}` | **{$name}** | {$description} |";
    }
    $result_table = implode("\n", $rows);

    $rows = array();
    $rows[] = "| {$head_key} | {$head_type} | {$head_desc} |";
    $rows[] = '|-------------|--------------|--------------|';
    $lint_spec = HarbormasterBuildLintMessage::getParameterSpec();
    foreach ($lint_spec as $key => $parameter) {
      $type = idx($parameter, 'type');
      $type = str_replace('|', ' '.pht('or').' ', $type);
      $description = idx($parameter, 'description');
      $rows[] = "| `{$key}` | //{$type}// | {$description} |";
    }
    $lint_table = implode("\n", $rows);

    $rows = array();
    $rows[] = "| {$head_key} | {$head_name} |";
    $rows[] = '|-------------|--------------|';
    $severities = ArcanistLintSeverity::getLintSeverities();
    foreach ($severities as $key => $name) {
      $rows[] = "| `{$key}` | **{$name}** |";
    }
    $severity_table = implode("\n", $rows);

    $valid_unit = array(
      array(
        'name' => 'PassingTest',
        'result' => ArcanistUnitTestResult::RESULT_PASS,
      ),
      array(
        'name' => 'FailingTest',
        'result' => ArcanistUnitTestResult::RESULT_FAIL,
      ),
    );

    $valid_lint = array(
      array(
        'name' => pht('Syntax Error'),
        'code' => 'EXAMPLE1',
        'severity' => ArcanistLintSeverity::SEVERITY_ERROR,
        'path' => 'path/to/example.c',
        'line' => 17,
        'char' => 3,
      ),
      array(
        'name' => pht('Not A Haiku'),
        'code' => 'EXAMPLE2',
        'severity' => ArcanistLintSeverity::SEVERITY_ERROR,
        'path' => 'path/to/source.cpp',
        'line' => 23,
        'char' => 1,
        'description' => pht(
          'This function definition is not a haiku.'),
      ),
    );

    $json = new PhutilJSON();
    $valid_unit = $json->encodeAsList($valid_unit);
    $valid_lint = $json->encodeAsList($valid_lint);

    return pht(
      "Send a message about the status of a build target to Harbormaster, ".
      "notifying the application of build results in an external system.".
      "\n\n".
      "Sending Messages\n".
      "================\n".
      "If you run external builds, you can use this method to publish build ".
      "results back into Harbormaster after the external system finishes work ".
      "or as it makes progress.".
      "\n\n".
      "The simplest way to use this method is to call it once after the ".
      "build finishes with a `pass` or `fail` message. This will record the ".
      "build result, and continue the next step in the build if the build was ".
      "waiting for a result.".
      "\n\n".
      "When you send a status message about a build target, you can ".
      "optionally include detailed `lint` or `unit` results alongside the ".
      "message. See below for details.".
      "\n\n".
      "If you want to report intermediate results but a build hasn't ".
      "completed yet, you can use the `work` message. This message doesn't ".
      "have any direct effects, but allows you to send additional data to ".
      "update the progress of the build target. The target will continue ".
      "waiting for a completion message, but the UI will update to show the ".
      "progress which has been made.".
      "\n\n".
      "Message Types\n".
      "=============\n".
      "When you send Harbormaster a message, you must include a `type`, ".
      "which describes the overall state of the build. For example, use ".
      "`pass` to tell Harbomaster that a build completed successfully.".
      "\n\n".
      "Supported message types are:".
      "\n\n".
      "%s".
      "\n\n".
      "Unit Results\n".
      "============\n".
      "You can report test results alongside a message. The simplest way to ".
      "do this is to report all the results alongside a `pass` or `fail` ".
      "message, but you can also send a `work` message to report intermediate ".
      "results.\n\n".
      "To provide unit test results, pass a list of results in the `unit` ".
      "parameter. Each result shoud be a dictionary with these keys:".
      "\n\n".
      "%s".
      "\n\n".
      "The `result` parameter recognizes these test results:".
      "\n\n".
      "%s".
      "\n\n".
      "This is a simple, valid value for the `unit` parameter. It reports ".
      "one passing test and one failing test:\n\n".
      "\n\n".
      "```lang=json\n".
      "%s".
      "```".
      "\n\n".
      "Lint Results\n".
      "============\n".
      "Like unit test results, you can report lint results alongside a ".
      "message. The `lint` parameter should contain results as a list of ".
      "dictionaries with these keys:".
      "\n\n".
      "%s".
      "\n\n".
      "The `severity` parameter recognizes these severity levels:".
      "\n\n".
      "%s".
      "\n\n".
      "This is a simple, valid value for the `lint` parameter. It reports one ".
      "error and one warning:".
      "\n\n".
      "```lang=json\n".
      "%s".
      "```".
      "\n\n",
      $message_table,
      $unit_table,
      $result_table,
      $valid_unit,
      $lint_table,
      $severity_table,
      $valid_lint);
  }

  protected function defineParamTypes() {
    $messages = HarbormasterMessageType::getAllMessages();
    $type_const = $this->formatStringConstants($messages);

    return array(
      'buildTargetPHID' => 'required phid',
      'type' => 'required '.$type_const,
      'unit' => 'optional list<wild>',
      'lint' => 'optional list<wild>',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $build_target_phid = $request->getValue('buildTargetPHID');
    $message_type = $request->getValue('type');

    $build_target = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($build_target_phid))
      ->executeOne();
    if (!$build_target) {
      throw new Exception(pht('No such build target!'));
    }

    $save = array();

    $lint_messages = $request->getValue('lint', array());
    foreach ($lint_messages as $lint) {
      $save[] = HarbormasterBuildLintMessage::newFromDictionary(
        $build_target,
        $lint);
    }

    $unit_messages = $request->getValue('unit', array());
    foreach ($unit_messages as $unit) {
      $save[] = HarbormasterBuildUnitMessage::newFromDictionary(
        $build_target,
        $unit);
    }

    $save[] = HarbormasterBuildMessage::initializeNewMessage($viewer)
      ->setBuildTargetPHID($build_target->getPHID())
      ->setType($message_type);

    $build_target->openTransaction();
    foreach ($save as $object) {
      $object->save();
    }
    $build_target->saveTransaction();

    // If the build has completely paused because all steps are blocked on
    // waiting targets, this will resume it.
    $build = $build_target->getBuild();

    PhabricatorWorker::scheduleTask(
      'HarbormasterBuildWorker',
      array(
        'buildID' => $build->getID(),
      ),
      array(
        'objectPHID' => $build->getPHID(),
      ));

    return null;
  }

}
