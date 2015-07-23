<?php

final class DifferentialFinishPostponedLintersConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.finishpostponedlinters';
  }

  public function getMethodDescription() {
    return pht(
      'Update diff with new lint messages and mark postponed '.
      'linters as finished.');
  }

  protected function defineParamTypes() {
    return array(
      'diffID'   => 'required diffID',
      'linters'  => 'required dict',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-DIFF'   => pht('Bad diff ID.'),
      'ERR-BAD-LINTER' => pht('No postponed linter by the given name.'),
      'ERR-NO-LINT'    => pht('No postponed lint field available in diff.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff_id = $request->getValue('diffID');
    $linter_map = $request->getValue('linters');

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($diff_id))
      ->executeOne();
    if (!$diff) {
      throw new ConduitException('ERR-BAD-DIFF');
    }

    // Extract the finished linters and messages from the linter map.
    $finished_linters = array_keys($linter_map);
    $new_messages = array();
    foreach ($linter_map as $linter => $messages) {
      $new_messages = array_merge($new_messages, $messages);
    }

    // Load the postponed linters attached to this diff.
    $postponed_linters_property = id(
      new DifferentialDiffProperty())->loadOneWhere(
        'diffID = %d AND name = %s',
        $diff_id,
        'arc:lint-postponed');
    if ($postponed_linters_property) {
      $postponed_linters = $postponed_linters_property->getData();
    } else {
      $postponed_linters = array();
    }

    foreach ($finished_linters as $linter) {
      if (!in_array($linter, $postponed_linters)) {
        throw new ConduitException('ERR-BAD-LINTER');
      }
    }

    foreach ($postponed_linters as $idx => $linter) {
      if (in_array($linter, $finished_linters)) {
        unset($postponed_linters[$idx]);
      }
    }

    // Load the lint messages currenty attached to the diff. If this
    // diff property doesn't exist, create it.
    $messages_property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $diff_id,
      'arc:lint');
    if ($messages_property) {
      $messages = $messages_property->getData();
    } else {
      $messages = array();
    }

    // Add new lint messages, removing duplicates.
    foreach ($new_messages as $new_message) {
      if (!in_array($new_message, $messages)) {
        $messages[] = $new_message;
      }
    }

    // Use setdiffproperty to update the postponed linters and messages,
    // as these will also update the lint status correctly.
    $call = new ConduitCall(
      'differential.setdiffproperty',
      array(
        'diff_id' => $diff_id,
        'name' => 'arc:lint',
        'data' => json_encode($messages),
      ));
    $call->setUser($request->getUser());
    $call->execute();
    $call = new ConduitCall(
      'differential.setdiffproperty',
      array(
        'diff_id' => $diff_id,
        'name' => 'arc:lint-postponed',
        'data' => json_encode($postponed_linters),
      ));
    $call->setUser($request->getUser());
    $call->execute();
  }
}
