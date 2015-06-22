<?php

final class HarbormasterAutotargetsTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testGenerateHarbormasterAutotargets() {
    $viewer = $this->generateNewTestUser();

    $raw_diff = <<<EODIFF
diff --git a/fruit b/fruit
new file mode 100644
index 0000000..1c0f49d
--- /dev/null
+++ b/fruit
@@ -0,0 +1,2 @@
+apal
+banan
EODIFF;

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($raw_diff);

    $diff = DifferentialDiff::newFromRawChanges($viewer, $changes)
      ->setLintStatus(DifferentialLintStatus::LINT_AUTO_SKIP)
      ->setUnitStatus(DifferentialUnitStatus::UNIT_AUTO_SKIP)
      ->attachRevision(null)
      ->save();

    $params = array(
      'objectPHID' => $diff->getPHID(),
      'targetKeys' => array(
        HarbormasterArcLintBuildStepImplementation::STEPKEY,
        HarbormasterArcUnitBuildStepImplementation::STEPKEY,
      ),
    );

    // Creation of autotargets should work from an empty state.
    $result = id(new ConduitCall('harbormaster.queryautotargets', $params))
      ->setUser($viewer)
      ->execute();

    $targets = idx($result, 'targetMap');
    foreach ($params['targetKeys'] as $target_key) {
      $this->assertTrue((bool)$result['targetMap'][$target_key]);
    }

    // Querying the same autotargets again should produce the same results,
    // not make new ones.
    $retry = id(new ConduitCall('harbormaster.queryautotargets', $params))
      ->setUser($viewer)
      ->execute();

    $this->assertEqual($result, $retry);
  }

}
