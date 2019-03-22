<?php

final class HarbormasterBuildPlanBehavior
  extends Phobject {

  private $key;
  private $name;
  private $options;
  private $defaultKey;
  private $editInstructions;

  const BEHAVIOR_RUNNABLE = 'runnable';
  const RUNNABLE_IF_VIEWABLE = 'view';
  const RUNNABLE_IF_EDITABLE = 'edit';

  const BEHAVIOR_RESTARTABLE = 'restartable';
  const RESTARTABLE_ALWAYS = 'always';
  const RESTARTABLE_IF_FAILED = 'failed';
  const RESTARTABLE_NEVER = 'never';

  const BEHAVIOR_DRAFTS = 'hold-drafts';
  const DRAFTS_ALWAYS = 'always';
  const DRAFTS_IF_BUILDING = 'building';
  const DRAFTS_NEVER = 'never';

  const BEHAVIOR_BUILDABLE = 'buildable';
  const BUILDABLE_ALWAYS = 'always';
  const BUILDABLE_IF_BUILDING = 'building';
  const BUILDABLE_NEVER = 'never';

  const BEHAVIOR_LANDWARNING = 'arc-land';
  const LANDWARNING_ALWAYS = 'always';
  const LANDWARNING_IF_BUILDING = 'building';
  const LANDWARNING_IF_COMPLETE = 'complete';
  const LANDWARNING_NEVER = 'never';

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setEditInstructions($edit_instructions) {
    $this->editInstructions = $edit_instructions;
    return $this;
  }

  public function getEditInstructions() {
    return $this->editInstructions;
  }

  public function getOptionMap() {
    return mpull($this->options, 'getName', 'getKey');
  }

  public function setOptions(array $options) {
    assert_instances_of($options, 'HarbormasterBuildPlanBehaviorOption');

    $key_map = array();
    $default = null;

    foreach ($options as $option) {
      $key = $option->getKey();

      if (isset($key_map[$key])) {
        throw new Exception(
          pht(
            'Multiple behavior options (for behavior "%s") have the same '.
            'key ("%s"). Each option must have a unique key.',
            $this->getKey(),
            $key));
      }
      $key_map[$key] = true;

      if ($option->getIsDefault()) {
        if ($default === null) {
          $default = $key;
        } else {
          throw new Exception(
            pht(
              'Multiple behavior options (for behavior "%s") are marked as '.
              'default options ("%s" and "%s"). Exactly one option must be '.
              'marked as the default option.',
              $this->getKey(),
              $default,
              $key));
        }
      }
    }

    if ($default === null) {
      throw new Exception(
        pht(
          'No behavior option is marked as the default option (for '.
          'behavior "%s"). Exactly one option must be marked as the '.
          'default option.',
          $this->getKey()));
    }

    $this->options = mpull($options, null, 'getKey');
    $this->defaultKey = $default;

    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function getPlanOption(HarbormasterBuildPlan $plan) {
    $behavior_key = $this->getKey();
    $storage_key = self::getStorageKeyForBehaviorKey($behavior_key);

    $plan_value = $plan->getPlanProperty($storage_key);
    if (isset($this->options[$plan_value])) {
      return $this->options[$plan_value];
    }

    return idx($this->options, $this->defaultKey);
  }

  public static function getTransactionMetadataKey() {
    return 'behavior-key';
  }

  public static function getStorageKeyForBehaviorKey($behavior_key) {
    return sprintf('behavior.%s', $behavior_key);
  }

  public static function getBehavior($key) {
    $behaviors = self::newPlanBehaviors();

    if (!isset($behaviors[$key])) {
      throw new Exception(
        pht(
          'No build plan behavior with key "%s" exists.',
          $key));
    }

    return $behaviors[$key];
  }

  public static function newPlanBehaviors() {
    $draft_options = array(
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::DRAFTS_ALWAYS)
        ->setIcon('fa-check-circle-o green')
        ->setName(pht('Always'))
        ->setIsDefault(true)
        ->setDescription(
          pht(
            'Revisions are not sent for review until the build completes, '.
            'and are returned to the author for updates if the build fails.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::DRAFTS_IF_BUILDING)
        ->setIcon('fa-pause-circle-o yellow')
        ->setName(pht('If Building'))
        ->setDescription(
          pht(
            'Revisions are not sent for review until the build completes, '.
            'but they will be sent for review even if it fails.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::DRAFTS_NEVER)
        ->setIcon('fa-circle-o red')
        ->setName(pht('Never'))
        ->setDescription(
          pht(
            'Revisions are sent for review regardless of the status of the '.
            'build.')),
    );

    $land_options = array(
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::LANDWARNING_ALWAYS)
        ->setIcon('fa-check-circle-o green')
        ->setName(pht('Always'))
        ->setIsDefault(true)
        ->setDescription(
          pht(
            '"arc land" warns if the build is still running or has '.
            'failed.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::LANDWARNING_IF_BUILDING)
        ->setIcon('fa-pause-circle-o yellow')
        ->setName(pht('If Building'))
        ->setDescription(
          pht(
            '"arc land" warns if the build is still running, but ignores '.
            'the build if it has failed.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::LANDWARNING_IF_COMPLETE)
        ->setIcon('fa-dot-circle-o yellow')
        ->setName(pht('If Complete'))
        ->setDescription(
          pht(
            '"arc land" warns if the build has failed, but ignores the '.
            'build if it is still running.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::LANDWARNING_NEVER)
        ->setIcon('fa-circle-o red')
        ->setName(pht('Never'))
        ->setDescription(
          pht(
            '"arc land" never warns that the build is still running or '.
            'has failed.')),
    );

    $aggregate_options = array(
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::BUILDABLE_ALWAYS)
        ->setIcon('fa-check-circle-o green')
        ->setName(pht('Always'))
        ->setIsDefault(true)
        ->setDescription(
          pht(
            'The buildable waits for the build, and fails if the '.
            'build fails.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::BUILDABLE_IF_BUILDING)
        ->setIcon('fa-pause-circle-o yellow')
        ->setName(pht('If Building'))
        ->setDescription(
          pht(
            'The buildable waits for the build, but does not fail '.
            'if the build fails.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::BUILDABLE_NEVER)
        ->setIcon('fa-circle-o red')
        ->setName(pht('Never'))
        ->setDescription(
          pht(
            'The buildable does not wait for the build.')),
    );

    $restart_options = array(
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::RESTARTABLE_ALWAYS)
        ->setIcon('fa-repeat green')
        ->setName(pht('Always'))
        ->setIsDefault(true)
        ->setDescription(
          pht('The build may be restarted.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::RESTARTABLE_IF_FAILED)
        ->setIcon('fa-times-circle-o yellow')
        ->setName(pht('If Failed'))
        ->setDescription(
          pht('The build may be restarted if it has failed.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::RESTARTABLE_NEVER)
        ->setIcon('fa-times red')
        ->setName(pht('Never'))
        ->setDescription(
          pht('The build may not be restarted.')),
    );

    $run_options = array(
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::RUNNABLE_IF_EDITABLE)
        ->setIcon('fa-pencil green')
        ->setName(pht('If Editable'))
        ->setIsDefault(true)
        ->setDescription(
          pht('Only users who can edit the plan can run it manually.')),
      id(new HarbormasterBuildPlanBehaviorOption())
        ->setKey(self::RUNNABLE_IF_VIEWABLE)
        ->setIcon('fa-exclamation-triangle yellow')
        ->setName(pht('If Viewable'))
        ->setDescription(
          pht(
            'Any user who can view the plan can run it manually.')),
    );

    $behaviors = array(
      id(new self())
        ->setKey(self::BEHAVIOR_DRAFTS)
        ->setName(pht('Hold Drafts'))
        ->setEditInstructions(
          pht(
            'When users create revisions in Differential, the default '.
            'behavior is to hold them in the "Draft" state until all builds '.
            'pass. Once builds pass, the revisions promote and are sent for '.
            'review, which notifies reviewers.'.
            "\n\n".
            'The general intent of this workflow is to make sure reviewers '.
            'are only spending time on review once changes survive automated '.
            'tests. If a change does not pass tests, it usually is not '.
            'really ready for review.'.
            "\n\n".
            'If you want to promote revisions out of "Draft" before builds '.
            'pass, or promote revisions even when builds fail, you can '.
            'change the promotion behavior. This may be useful if you have '.
            'very long-running builds, or some builds which are not very '.
            'important.'.
            "\n\n".
            'Users may always use "Request Review" to promote a "Draft" '.
            'revision, even if builds have failed or are still in progress.'))
        ->setOptions($draft_options),
      id(new self())
        ->setKey(self::BEHAVIOR_LANDWARNING)
        ->setName(pht('Warn When Landing'))
        ->setEditInstructions(
          pht(
            'When a user attempts to `arc land` a revision and that revision '.
            'has ongoing or failed builds, the default behavior of `arc` is '.
            'to warn them about those builds and give them a chance to '.
            'reconsider: they may want to wait for ongoing builds to '.
            'complete, or fix failed builds before landing the change.'.
            "\n\n".
            'If you do not want to warn users about this build, you can '.
            'change the warning behavior. This may be useful if the build '.
            'takes a long time to run (so you do not expect users to wait '.
            'for it) or the outcome is not important.'.
            "\n\n".
            'This warning is only advisory. Users may always elect to ignore '.
            'this warning and continue, even if builds have failed.'.
            "\n\n".
            'This setting also affects the warning that is published to '.
            'revisions when commits land with ongoing or failed builds.'))
        ->setOptions($land_options),
      id(new self())
        ->setKey(self::BEHAVIOR_BUILDABLE)
        ->setEditInstructions(
          pht(
            'The overall state of a buildable (like a commit or revision) is '.
            'normally the aggregation of the individual states of all builds '.
            'that have run against it.'.
            "\n\n".
            'Buildables are "building" until all builds pass (which changes '.
            'them to "pass"), or any build fails (which changes them to '.
            '"fail").'.
            "\n\n".
            'You can change this behavior if you do not want to wait for this '.
            'build, or do not care if it fails.'))
        ->setName(pht('Affects Buildable'))
        ->setOptions($aggregate_options),
      id(new self())
        ->setKey(self::BEHAVIOR_RESTARTABLE)
        ->setEditInstructions(
          pht(
            'Usually, builds may be restarted by users who have permission '.
            'to edit the related build plan. (You can change who is allowed '.
            'to restart a build by adjusting the "Runnable" behavior.)'.
            "\n\n".
            'Restarting a build may be useful if you suspect it has failed '.
            'for environmental or circumstantial reasons unrelated to the '.
            'actual code, and want to give it another chance at glory.'.
            "\n\n".
            'If you want to prevent a build from being restarted, you can '.
            'change when it may be restarted by adjusting this behavior. '.
            'This may be useful to prevent accidents where a build with a '.
            'dangerous side effect (like deployment) is restarted '.
            'improperly.'))
        ->setName(pht('Restartable'))
        ->setOptions($restart_options),
      id(new self())
        ->setKey(self::BEHAVIOR_RUNNABLE)
        ->setEditInstructions(
          pht(
            'To run a build manually, you normally must have permission to '.
            'edit the related build plan. If you would prefer that anyone who '.
            'can see the build plan be able to run and restart the build, you '.
            'can change the behavior here.'.
            "\n\n".
            'Note that this controls access to all build management actions: '.
            '"Run Plan Manually", "Restart", "Abort", "Pause", and "Resume".'.
            "\n\n".
            'WARNING: This may be unsafe, particularly if the build has '.
            'side effects like deployment.'.
            "\n\n".
            'If you weaken this policy, an attacker with control of an '.
            'account that has "Can View" permission but not "Can Edit" '.
            'permission can manually run this build against any old version '.
            'of the code, including versions with known security issues.'.
            "\n\n".
            'If running the build has a side effect like deploying code, '.
            'they can force deployment of a vulnerable version and then '.
            'escalate into an attack against the deployed service.'))
        ->setName(pht('Runnable'))
        ->setOptions($run_options),
    );

    return mpull($behaviors, null, 'getKey');
  }

}
