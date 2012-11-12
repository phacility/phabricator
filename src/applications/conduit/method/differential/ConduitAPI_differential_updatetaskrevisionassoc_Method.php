<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_updatetaskrevisionassoc_Method
  extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "This method should not really exist. Pretend it doesn't.";
  }

  public function getMethodDescription() {
    return "Given a task together with its original and new associated ".
      "revisions, update the revisions for their attached_tasks.";
  }

  public function defineParamTypes() {
    return array(
      'task_phid' => 'required nonempty string',
      'orig_rev_phids' => 'required list<string>',
      'new_rev_phids' => 'required list<string>',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NO_TASKATTACHER_DEFINED' => 'No task attacher defined.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task_phid = $request->getValue('task_phid');
    $orig_rev_phids = $request->getValue('orig_rev_phids');
    if (empty($orig_rev_phids)) {
      $orig_rev_phids = array();
    }

    $new_rev_phids = $request->getValue('new_rev_phids');
    if (empty($new_rev_phids)) {
      $new_rev_phids = array();
    }

    try {
      $task_attacher = PhabricatorEnv::newObjectFromConfig(
        'differential.attach-task-class');
      $task_attacher->updateTaskRevisionAssoc(
        $task_phid,
        $orig_rev_phids,
        $new_rev_phids);
    } catch (ReflectionException $ex) {
      throw new ConduitException('ERR_NO_TASKATTACHER_DEFINED');
    }
  }

}

