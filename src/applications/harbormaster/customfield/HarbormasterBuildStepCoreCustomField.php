<?php

final class HarbormasterBuildStepCoreCustomField
  extends HarbormasterBuildStepCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'harbormaster:core';
  }

  public function createFields($object) {
    try {
      $impl = $object->getStepImplementation();
    } catch (Exception $ex) {
      return array();
    }

    $specs = $impl->getFieldSpecifications();

    if ($impl->supportsWaitForMessage()) {
      $specs['builtin.next-steps-header'] = array(
        'type' => 'header',
        'name' => pht('Next Steps'),
      );

      $specs['builtin.wait-for-message'] = array(
        'type' => 'select',
        'name' => pht('When Complete'),
        'instructions' => pht(
          'After completing this build step Harbormaster can continue the '.
          'build normally, or it can pause the build and wait for a message. '.
          'If you are using this build step to trigger some work in an '.
          'external system, you may want to have Phabricator wait for that '.
          'system to perform the work and report results back.'.
          "\n\n".
          'If you select **Continue Build Normally**, the build plan will '.
          'proceed once this step finishes.'.
          "\n\n".
          'If you select **Wait For Message**, the build plan will pause '.
          'indefinitely once this step finishes. To resume the build, an '.
          'external system must call `harbormaster.sendmessage` with the '.
          'build target PHID, and either `"pass"` or `"fail"` to indicate '.
          'the result for this step. After the result is recorded, the build '.
          'plan will resume.'),
        'options' => array(
          'continue' => pht('Continue Build Normally'),
          'wait' => pht('Wait For Message'),
        ),
      );
    }

    return PhabricatorStandardCustomField::buildStandardFields($this, $specs);
  }

  public function shouldUseStorage() {
    return false;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $key = $this->getProxy()->getRawStandardFieldKey();
    $this->setValueFromStorage($object->getDetail($key));
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $object = $this->getObject();
    $key = $this->getProxy()->getRawStandardFieldKey();

    $this->setValueFromApplicationTransactions($xaction->getNewValue());
    $value = $this->getValueForStorage();

    $object->setDetail($key, $value);
  }

  public function getBuildTargetFieldValue() {
    return $this->getProxy()->getFieldValue();
  }

}
