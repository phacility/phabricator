<?php

final class HarbormasterSendMessageConduitAPIMethod
  extends HarbormasterConduitAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.sendmessage';
  }

  public function getMethodSummary() {
    return pht(
      'Modify running builds, and report build results.');
  }

  public function getMethodDescription() {
    return pht(<<<EOREMARKUP
Pause, abort, restart, and report results for builds.
EOREMARKUP
      );
  }

  protected function newDocumentationPages(PhabricatorUser $viewer) {
    $pages = array();

    $pages[] = $this->newSendingDocumentationBoxPage($viewer);
    $pages[] = $this->newBuildsDocumentationBoxPage($viewer);
    $pages[] = $this->newCommandsDocumentationBoxPage($viewer);
    $pages[] = $this->newTargetsDocumentationBoxPage($viewer);
    $pages[] = $this->newUnitDocumentationBoxPage($viewer);
    $pages[] = $this->newLintDocumentationBoxPage($viewer);

    return $pages;
  }

  private function newSendingDocumentationBoxPage(PhabricatorUser $viewer) {
    $title = pht('Sending Messages');
    $content = pht(<<<EOREMARKUP
Harbormaster build objects work somewhat differently from objects in many other
applications. Most application objects can be edited directly using synchronous
APIs (like `maniphest.edit`, `differential.revision.edit`, and so on).

However, builds require long-running background processing and Habormaster
objects have a more complex lifecycle than most other application objects and
may spend significant periods of time locked by daemon processes during build
execition. A synchronous edit might need to wait an arbitrarily long amount of
time for this lock to become available so the edit could be applied.

Additionally, some edits may also require an arbitrarily long amount of time to
//complete//. For example, aborting a build may execute cleanup steps which
take minutes (or even hours) to complete.

Since a synchronous API could not guarantee it could return results to the
caller in a reasonable amount of time, the edit API for Harbormaster build
objects is asynchronous: to update a Harbormaster build or build target, use
this API (`harbormaster.sendmessage`) to send it a message describing an edit
you would like to effect or additional information you want to provide.
The message will be processed by the daemons once the build or target reaches
a suitable state to receive messages.

Select an object to send a message to using the `receiver` parameter. This
API method can send messages to multiple types of objects:

<table>
  <tr>
    <th>Object Type</th>
    <th>PHID Example</th>
    <th>Description</th>
  </tr>
  <tr>
    <td>Harbormaster Buildable</td>
    <td>`PHID-HMBB-...`</td>
    <td>%s</td>
  </tr>
  <tr>
    <td>Harbormaster Build</td>
    <td>`PHID-HMBD-...`</td>
    <td>%s</td>
  </tr>
  <tr>
    <td>Harbormaster Build Target</td>
    <td>`PHID-HMBT-...`</td>
    <td>%s</td>
  </tr>
</table>

See below for specifics on sending messages to different object types.
EOREMARKUP
      ,
      pht(
        'Buildables may receive control commands like "abort" and "restart". '.
        'Sending a control command to a Buildable is the same as sending it '.
        'to each Build for the Buildable.'),
      pht(
        'Builds may receive control commands like "pause", "resume", "abort", '.
        'and "restart".'),
      pht(
        'Build Targets may receive build status and result messages, like '.
        '"pass" or "fail".'));

    $content = $this->newRemarkupDocumentationView($content);

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('sending')
      ->setIconIcon('fa-envelope-o');
  }

  private function newBuildsDocumentationBoxPage(PhabricatorUser $viewer) {
    $title = pht('Updating Builds');

    $content = pht(<<<EOREMARKUP
You can use this method (`harbormaster.sendmessage`) to send control commands
to Buildables and Builds.

Specify the Build or Buildable to receive the control command by providing its
PHID in the `receiver` parameter.

Sending a control command to a Buildable has the same effect as sending it to
each Build for the Buildable. For example, sending a "Pause" message to a
Buildable will pause all builds for the Buildable (or at least attempt to).

When sending control commands, the `unit` and `lint` parameters of this API
method must be omitted. You can not report lint or unit results directly to
a Build or Buildable, and can not report them alongside a control command.

More broadly, you can not report build results directly to a Build or
Buildable. Instead, report results to a Build Target.

See below for a list of control commands.

EOREMARKUP
      );

    $content = $this->newRemarkupDocumentationView($content);

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('builds')
      ->setIconIcon('fa-cubes');
  }

  private function newCommandsDocumentationBoxPage(PhabricatorUser $viewer) {
    $messages = HarbormasterBuildMessageTransaction::getAllMessages();

    $rows = array();

    $rows[] = '<tr>';
    $rows[] = '<th>'.pht('Message Type').'</th>';
    $rows[] = '<th>'.pht('Description').'</th>';
    $rows[] = '</tr>';

    foreach ($messages as $message) {
      $row = array();

      $row[] = sprintf(
        '<td>`%s`</td>',
        $message->getHarbormasterBuildMessageType());

      $row[] = sprintf(
        '<td>%s</td>',
        $message->getHarbormasterBuildMessageDescription());

      $rows[] = sprintf(
        '<tr>%s</tr>',
        implode("\n", $row));
    }

    $message_table = sprintf(
      '<table>%s</table>',
      implode("\n", $rows));

    $title = pht('Control Commands');

    $content = pht(<<<EOREMARKUP
You can use this method to send control commands to Buildables and Builds.

This table summarizes which object types may receive control commands:

<table>
  <tr>
    <th>Object Type</th>
    <th>PHID Example</th>
    <th />
    <th>Description</th>
  </tr>
  <tr>
    <td>Harbormaster Buildable</td>
    <td>`PHID-HMBB-...`</td>
    <td>{icon check color=green}</td>
    <td>Buildables may receive control commands.</td>
  </tr>
  <tr>
    <td>Harbormaster Build</td>
    <td>`PHID-HMBD-...`</td>
    <td>{icon check color=green}</td>
    <td>Builds may receive control commands.</td>
  </tr>
  <tr>
    <td>Harbormaster Build Target</td>
    <td>`PHID-HMBT-...`</td>
    <td>{icon times color=red}</td>
    <td>You may **NOT** send control commands to build targets.</td>
  </tr>
</table>

You can send these commands:

%s

To send a command message, specify the PHID of the object you would like to
receive the message using the `receiver` parameter, and specify the message
type using the `type` parameter.

EOREMARKUP
      ,
      $message_table);

    $content = $this->newRemarkupDocumentationView($content);

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('commands')
      ->setIconIcon('fa-exclamation-triangle');
  }

  private function newTargetsDocumentationBoxPage(PhabricatorUser $viewer) {
    $messages = HarbormasterMessageType::getAllMessages();

    $head_type = pht('Type');
    $head_desc = pht('Description');

    $rows = array();
    $rows[] = "| {$head_type} | {$head_desc} |";
    $rows[] = '|--------------|--------------|';
    foreach ($messages as $message) {
      $description = HarbormasterMessageType::getMessageDescription($message);
      $rows[] = "| `{$message}` | {$description} |";
    }
    $message_table = implode("\n", $rows);

    $content = pht(<<<EOREMARKUP
If you run external builds, you can use this method to publish build results
back into Harbormaster after the external system finishes work (or as it makes
progress).

To report build status or results, you must send a message to the appropriate
Build Target. This table summarizes which object types may receive build status
and result messages:

<table>
  <tr>
    <th>Object Type</th>
    <th>PHID Example</th>
    <th />
    <th>Description</th>
  </tr>
  <tr>
    <td>Harbormaster Buildable</td>
    <td>`PHID-HMBB-...`</td>
    <td>{icon times color=red}</td>
    <td>Buildables may **NOT** receive status or result messages.</td>
  </tr>
  <tr>
    <td>Harbormaster Build</td>
    <td>`PHID-HMBD-...`</td>
    <td>{icon times color=red}</td>
    <td>Builds may **NOT** receive status or result messages.</td>
  </tr>
  <tr>
    <td>Harbormaster Build Target</td>
    <td>`PHID-HMBT-...`</td>
    <td>{icon check color=green}</td>
    <td>Report build status and results to Build Targets.</td>
  </tr>
</table>

The simplest way to use this method to report build results is to call it once
after the build finishes with a `pass` or `fail` message. This will record the
build result, and continue the next step in the build if the build was waiting
for a result.

When you send a status message about a build target, you can optionally include
detailed `lint` or `unit` results alongside the message. See below for details.

If you want to report intermediate results but a build hasn't completed yet,
you can use the `work` message. This message doesn't have any direct effects,
but allows you to send additional data to update the progress of the build
target. The target will continue waiting for a completion message, but the UI
will update to show the progress which has been made.

When sending a message to a build target to report the status or results of
a build, your message must include a `type` which describes the overall state
of the build. For example, use `pass` to tell Harbormaster that a build target
completed successfully.

Supported message types are:

%s

EOREMARKUP
      ,
      $message_table);

    $title = pht('Updating Build Targets');

    $content = $this->newRemarkupDocumentationView($content);

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('targets')
      ->setIconIcon('fa-bullseye');
  }

  private function newUnitDocumentationBoxPage(PhabricatorUser $viewer) {
    $head_key = pht('Key');
    $head_desc = pht('Description');
    $head_name = pht('Name');
    $head_type = pht('Type');

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

    $json = new PhutilJSON();
    $valid_unit = $json->encodeAsList($valid_unit);


    $title = pht('Reporting Unit Results');

    $content = pht(<<<EOREMARKUP
You can report test results when updating the state of a build target. The
simplest way to do this is to report all the results alongside a `pass` or
`fail` message, but you can also send a `work` message to report intermediate
results.


To provide unit test results, pass a list of results in the `unit`
parameter. Each result should be a dictionary with these keys:

%s

The `result` parameter recognizes these test results:

%s

This is a simple, valid value for the `unit` parameter. It reports one passing
test and one failing test:

```lang=json
%s
```
EOREMARKUP
      ,
      $unit_table,
      $result_table,
      $valid_unit);

    $content = $this->newRemarkupDocumentationView($content);

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('unit');
  }

  private function newLintDocumentationBoxPage(PhabricatorUser $viewer) {

    $head_key = pht('Key');
    $head_desc = pht('Description');
    $head_name = pht('Name');
    $head_type = pht('Type');

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
    $valid_lint = $json->encodeAsList($valid_lint);

    $title = pht('Reporting Lint Results');
    $content = pht(<<<EOREMARKUP
Like unit test results, you can report lint results when updating the state
of a build target. The `lint` parameter should contain results as a list of
dictionaries with these keys:

%s

The `severity` parameter recognizes these severity levels:

%s

This is a simple, valid value for the `lint` parameter. It reports one error
and one warning:

```lang=json
%s
```

EOREMARKUP
      ,
      $lint_table,
      $severity_table,
      $valid_lint);

    $content = $this->newRemarkupDocumentationView($content);

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('lint');
  }

  protected function defineParamTypes() {
    $messages = HarbormasterMessageType::getAllMessages();

    $more_messages = HarbormasterBuildMessageTransaction::getAllMessages();
    $more_messages = mpull($more_messages, 'getHarbormasterBuildMessageType');

    $messages = array_merge($messages, $more_messages);
    $messages = array_unique($messages);

    sort($messages);

    $type_const = $this->formatStringConstants($messages);

    return array(
      'receiver' => 'required string|phid',
      'type' => 'required '.$type_const,
      'unit' => 'optional list<wild>',
      'lint' => 'optional list<wild>',
      'buildTargetPHID' => 'deprecated optional phid',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getViewer();

    $receiver_name = $request->getValue('receiver');

    $build_target_phid = $request->getValue('buildTargetPHID');
    if ($build_target_phid !== null) {
      if ($receiver_name === null) {
        $receiver_name = $build_target_phid;
      } else {
        throw new Exception(
          pht(
            'Call specifies both "receiver" and "buildTargetPHID". '.
            'When using the modern "receiver" parameter, omit the '.
            'deprecated "buildTargetPHID" parameter.'));
      }
    }

    if ($receiver_name === null || !strlen($receiver_name)) {
      throw new Exception(
        pht(
          'Call omits required "receiver" parameter. Specify the PHID '.
          'of the object you want to send a message to.'));
    }

    $message_type = $request->getValue('type');
    if ($message_type === null || !strlen($message_type)) {
      throw new Exception(
        pht(
          'Call omits required "type" parameter. Specify the type of '.
          'message you want to send.'));
    }

    $receiver_object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($receiver_name))
      ->executeOne();
    if (!$receiver_object) {
      throw new Exception(
        pht(
          'Unable to load object "%s" to receive message.',
          $receiver_name));
    }

    $is_target = ($receiver_object instanceof HarbormasterBuildTarget);
    if ($is_target) {
      return $this->sendToTarget($request, $message_type, $receiver_object);
    }

    if ($request->getValue('unit') !== null) {
      throw new Exception(
        pht(
          'Call includes "unit" parameter. This parameter must be omitted '.
          'when the receiver is not a Build Target.'));
    }

    if ($request->getValue('lint') !== null) {
      throw new Exception(
        pht(
          'Call includes "lint" parameter. This parameter must be omitted '.
          'when the receiver is not a Build Target.'));
    }

    $is_build = ($receiver_object instanceof HarbormasterBuild);
    if ($is_build) {
      return $this->sendToBuild($request, $message_type, $receiver_object);
    }

    $is_buildable = ($receiver_object instanceof HarbormasterBuildable);
    if ($is_buildable) {
      return $this->sendToBuildable($request, $message_type, $receiver_object);
    }

    throw new Exception(
      pht(
        'Receiver object (of class "%s") is not a valid receiver.',
        get_class($receiver_object)));
  }

  private function sendToTarget(
    ConduitAPIRequest $request,
    $message_type,
    HarbormasterBuildTarget $build_target) {
    $viewer = $request->getViewer();

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
      ->setReceiverPHID($build_target->getPHID())
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

  private function sendToBuild(
    ConduitAPIRequest $request,
    $message_type,
    HarbormasterBuild $build) {
    $viewer = $request->getViewer();

    $xaction =
      HarbormasterBuildMessageTransaction::getTransactionObjectForMessageType(
        $message_type);
    if (!$xaction) {
      throw new Exception(
        pht(
          'Message type "%s" is not supported.',
          $message_type));
    }

    // NOTE: This is a slightly weaker check than we perform in the web UI.
    // We allow API callers to send a "pause" message to a pausing build,
    // for example, even though the message will have no effect.
    $xaction->assertCanApplyMessage($viewer, $build);

    $build->sendMessage($viewer, $xaction->getHarbormasterBuildMessageType());
  }

  private function sendToBuildable(
    ConduitAPIRequest $request,
    $message_type,
    HarbormasterBuildable $buildable) {
    $viewer = $request->getViewer();

    $xaction =
      HarbormasterBuildMessageTransaction::getTransactionObjectForMessageType(
        $message_type);
    if (!$xaction) {
      throw new Exception(
        pht(
          'Message type "%s" is not supported.',
          $message_type));
    }

    // Reload the Buildable to load Builds.
    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($buildable->getID()))
      ->needBuilds(true)
      ->executeOne();

    $can_send = array();
    foreach ($buildable->getBuilds() as $build) {
      if ($xaction->canApplyMessage($viewer, $build)) {
        $can_send[] = $build;
      }
    }

    // NOTE: This doesn't actually apply a transaction to the Buildable,
    // but that transaction is purely informational and should probably be
    // implemented as a Message.

    foreach ($can_send as $build) {
      $build->sendMessage($viewer, $xaction->getHarbormasterBuildMessageType());
    }
  }

}
