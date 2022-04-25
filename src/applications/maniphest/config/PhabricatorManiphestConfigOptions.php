<?php

final class PhabricatorManiphestConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Maniphest');
  }

  public function getDescription() {
    return pht('Configure Maniphest.');
  }

  public function getIcon() {
    return 'fa-anchor';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    $priority_type = 'maniphest.priorities';
    $priority_defaults = array(
      100 => array(
        'name'  => pht('Unbreak Now!'),
        'keywords' => array('unbreak'),
        'short' => pht('Unbreak!'),
        'color' => 'pink',
      ),
      90 => array(
        'name' => pht('Needs Triage'),
        'keywords' => array('triage'),
        'short' => pht('Triage'),
        'color' => 'violet',
      ),
      80 => array(
        'name' => pht('High'),
        'keywords' => array('high'),
        'short' => pht('High'),
        'color' => 'red',
      ),
      50 => array(
        'name' => pht('Normal'),
        'keywords' => array('normal'),
        'short' => pht('Normal'),
        'color' => 'orange',
      ),
      25 => array(
        'name' => pht('Low'),
        'keywords' => array('low'),
        'short' => pht('Low'),
        'color' => 'yellow',
      ),
      0 => array(
        'name' => pht('Wishlist'),
        'keywords' => array('wish', 'wishlist'),
        'short' => pht('Wish'),
        'color' => 'sky',
      ),
    );

    $status_type = 'maniphest.statuses';
    $status_defaults = array(
      'open' => array(
        'name' => pht('Open'),
        'special' => ManiphestTaskStatus::SPECIAL_DEFAULT,
        'prefixes' => array(
          'open',
          'opens',
          'reopen',
          'reopens',
        ),
      ),
      'resolved' => array(
        'name' => pht('Resolved'),
        'name.full' => pht('Closed, Resolved'),
        'closed' => true,
        'special' => ManiphestTaskStatus::SPECIAL_CLOSED,
        'transaction.icon' => 'fa-check-circle',
        'prefixes' => array(
          'closed',
          'closes',
          'close',
          'fix',
          'fixes',
          'fixed',
          'resolve',
          'resolves',
          'resolved',
        ),
        'suffixes' => array(
          'as resolved',
          'as fixed',
        ),
        'keywords' => array('closed', 'fixed', 'resolved'),
      ),
      'wontfix' => array(
        'name' => pht('Wontfix'),
        'name.full' => pht('Closed, Wontfix'),
        'transaction.icon' => 'fa-ban',
        'closed' => true,
        'prefixes' => array(
          'wontfix',
          'wontfixes',
          'wontfixed',
        ),
        'suffixes' => array(
          'as wontfix',
        ),
      ),
      'invalid' => array(
        'name' => pht('Invalid'),
        'name.full' => pht('Closed, Invalid'),
        'transaction.icon' => 'fa-minus-circle',
        'closed' => true,
        'claim' => false,
        'prefixes' => array(
          'invalidate',
          'invalidates',
          'invalidated',
        ),
        'suffixes' => array(
          'as invalid',
        ),
      ),
      'duplicate' => array(
        'name' => pht('Duplicate'),
        'name.full' => pht('Closed, Duplicate'),
        'transaction.icon' => 'fa-files-o',
        'special' => ManiphestTaskStatus::SPECIAL_DUPLICATE,
        'closed' => true,
        'claim' => false,
      ),
      'spite' => array(
        'name' => pht('Spite'),
        'name.full' => pht('Closed, Spite'),
        'name.action' => pht('Spited'),
        'transaction.icon' => 'fa-thumbs-o-down',
        'silly' => true,
        'closed' => true,
        'prefixes' => array(
          'spite',
          'spites',
          'spited',
        ),
        'suffixes' => array(
          'out of spite',
          'as spite',
        ),
      ),
    );

    $status_description = $this->deformat(pht(<<<EOTEXT
Allows you to edit, add, or remove the task statuses available in Maniphest,
like "Open", "Resolved" and "Invalid". The configuration should contain a map
of status constants to status specifications (see defaults below for examples).

The constant for each status should be 1-12 characters long and  contain only
lowercase letters and digits. Valid examples are "open", "closed", and
"invalid". Users will not normally see these values.

The keys you can provide in a specification are:

  - `name` //Required string.// Name of the status, like "Invalid".
  - `name.full` //Optional string.// Longer name, like "Closed, Invalid". This
    appears on the task detail view in the header.
  - `name.action` //Optional string.// Action name for email subjects, like
    "Marked Invalid".
  - `closed` //Optional bool.// Statuses are either "open" or "closed".
    Specifying `true` here will mark the status as closed (like "Resolved" or
    "Invalid"). By default, statuses are open.
  - `special` //Optional string.// Mark this status as special. The special
    statuses are:
    - `default` This is the default status for newly created tasks. You must
      designate one status as default, and it must be an open status.
    - `closed` This is the default status for closed tasks (for example, tasks
      closed via the "!close" action in email or via the quick close button in
      Maniphest). You must designate one status as the default closed status,
      and it must be a closed status.
    - `duplicate` This is the status used when tasks are merged into one
      another as duplicates. You must designate one status for duplicates,
      and it must be a closed status.
  - `transaction.icon` //Optional string.// Allows you to choose a different
    icon to use for this status when showing status changes in the transaction
    log. Please see UIExamples, Icons and Images for a list.
  - `transaction.color` //Optional string.// Allows you to choose a different
    color to use for this status when showing status changes in the transaction
    log.
  - `silly` //Optional bool.// Marks this status as silly, and thus wholly
    inappropriate for use by serious businesses.
  - `prefixes` //Optional list<string>.// Allows you to specify a list of
    text prefixes which will trigger a task transition into this status
    when mentioned in a commit message. For example, providing "closes" here
    will allow users to move tasks to this status by writing `Closes T123` in
    commit messages.
  - `suffixes` //Optional list<string>.// Allows you to specify a list of
    text suffixes which will trigger a task transition into this status
    when mentioned in a commit message, after a valid prefix. For example,
    providing "as invalid" here will allow users to move tasks
    to this status by writing `Closes T123 as invalid`, even if another status
    is selected by the "Closes" prefix.
  - `keywords` //Optional list<string>.// Allows you to specify a list
    of keywords which can be used with `!status` commands in email to select
    this status.
  - `disabled` //Optional bool.// Marks this status as no longer in use so
    tasks can not be created or edited to have this status. Existing tasks with
    this status will not be affected, but you can batch edit them or let them
    die out on their own.
  - `claim` //Optional bool.// By default, closing an unassigned task claims
    it. You can set this to `false` to disable this behavior for a particular
    status.
  - `locked` //Optional string.// Lock tasks in this status. Specify "comments"
    to lock comments (users who can edit the task may override this lock).
    Specify "edits" to prevent anyone except the task owner from making edits.
  - `mfa` //Optional bool.// Require all edits to this task to be signed with
    multi-factor authentication.

Statuses will appear in the UI in the order specified. Note the status marked
`special` as `duplicate` is not settable directly and will not appear in UI
elements, and that any status marked `silly` does not appear if the software
is configured with `phabricator.serious-business` set to true.

Examining the default configuration and examples below will probably be helpful
in understanding these options.

EOTEXT
));

    $status_example = array(
      'open' => array(
        'name' => pht('Open'),
        'special' => 'default',
      ),
      'closed' => array(
        'name' => pht('Closed'),
        'special' => 'closed',
        'closed' => true,
      ),
      'duplicate' => array(
        'name' => pht('Duplicate'),
        'special' => 'duplicate',
        'closed' => true,
      ),
    );

    $json = new PhutilJSON();
    $status_example = $json->encodeFormatted($status_example);

    // This is intentionally blank for now, until we can move more Maniphest
    // logic to custom fields.
    $default_fields = array();

    foreach ($default_fields as $key => $enabled) {
      $default_fields[$key] = array(
        'disabled' => !$enabled,
      );
    }

    $custom_field_type = 'custom:PhabricatorCustomFieldConfigOptionType';

    $fields_example = array(
      'mycompany.estimated-hours' => array(
        'name' => pht('Estimated Hours'),
        'type' => 'int',
        'caption' => pht('Estimated number of hours this will take.'),
      ),
    );
    $fields_json = id(new PhutilJSON())->encodeFormatted($fields_example);

    $points_type = 'maniphest.points';

    $points_example_1 = array(
      'enabled' => true,
      'label' => pht('Story Points'),
      'action' => pht('Change Story Points'),
    );
    $points_json_1 = id(new PhutilJSON())->encodeFormatted($points_example_1);

    $points_example_2 = array(
      'enabled' => true,
      'label' => pht('Estimated Hours'),
      'action' => pht('Change Estimate'),
    );
    $points_json_2 = id(new PhutilJSON())->encodeFormatted($points_example_2);

    $points_description = $this->deformat(pht(<<<EOTEXT
Activates a points field on tasks. You can use points for estimation or
planning. If configured, points will appear on workboards.

To activate points, set this value to a map with these keys:

  - `enabled` //Optional bool.// Use `true` to enable points, or
    `false` to disable them.
  - `label` //Optional string.// Label for points, like "Story Points" or
    "Estimated Hours". If omitted, points will be called "Points".
  - `action` //Optional string.// Label for the action which changes points
    in Maniphest, like "Change Estimate". If omitted, the action will
    be called "Change Points".

See the example below for a starting point.
EOTEXT
));

    $subtype_type = 'maniphest.subtypes';
    $subtype_default_key = PhabricatorEditEngineSubtype::SUBTYPE_DEFAULT;
    $subtype_example = array(
      array(
        'key' => $subtype_default_key,
        'name' => pht('Task'),
      ),
      array(
        'key' => 'bug',
        'name' => pht('Bug'),
      ),
      array(
        'key' => 'feature',
        'name' => pht('Feature Request'),
      ),
    );
    $subtype_example = id(new PhutilJSON())->encodeAsList($subtype_example);

    $subtype_default = array(
      array(
        'key' => $subtype_default_key,
        'name' => pht('Task'),
      ),
    );

    $subtype_description = $this->deformat(pht(<<<EOTEXT
Allows you to define task subtypes. Subtypes let you hide fields you don't
need to simplify the workflows for editing tasks.

To define subtypes, provide a list of subtypes. Each subtype should be a
dictionary with these keys:

  - `key` //Required string.// Internal identifier for the subtype, like
    "task", "feature", or "bug".
  - `name` //Required string.// Human-readable name for this subtype, like
    "Task", "Feature Request" or "Bug Report".
  - `tag` //Optional string.// Tag text for this subtype.
  - `color` //Optional string.// Display color for this subtype.
  - `icon` //Optional string.// Icon for the subtype.
  - `children` //Optional map.// Configure options shown to the user when
     they "Create Subtask". See below.
  - `fields` //Optional map.// Configure field behaviors. See below.
  - `mutations` //Optional list.// Configure which subtypes this subtype
    can easily be converted to by using the "Change Subtype" action. See below.

Each subtype must have a unique key, and you must define a subtype with
the key "%s", which is used as a default subtype.

The tag text (`tag`) is used to set the text shown in the subtype tag on list
views and workboards. If you do not configure it, the default subtype will have
no subtype tag and other subtypes will use their name as tag text.

The `children` key allows you to configure which options are presented to the
user when they "Create Subtask" from a task of this subtype. You can specify
these keys:

  - `subtypes`: //Optional list<string>.// Show users creation forms for these
    task subtypes.
  - `forms`: //Optional list<string|int>.// Show users these specific forms,
    in order.

If you don't specify either constraint, users will be shown creation forms
for the same subtype.

For example, if you have a "quest" subtype and do not configure `children`,
users who click "Create Subtask" will be presented with all create forms for
"quest" tasks.

If you want to present them with forms for a different task subtype or set of
subtypes instead, use `subtypes`:

```
  {
    ...
    "children": {
      "subtypes": ["objective", "boss", "reward"]
    }
    ...
  }
```

If you want to present them with specific forms, use `forms` and specify form
IDs:

```
  {
    ...
    "children": {
      "forms": [12, 16]
    }
    ...
  }
```

When specifying forms by ID explicitly, the order you specify the forms in will
be used when presenting options to the user.

If only one option would be presented, the user will be taken directly to the
appropriate form instead of being prompted to choose a form.

The `fields` key can configure the behavior of custom fields on specific
task subtypes. For example:

```
  {
    ...
    "fields": {
      "custom.some-field": {
        "disabled": true
      }
    }
    ...
  }
```

Each field supports these options:

  - `disabled` //Optional bool.// Allows you to disable fields on certain
    subtypes.
  - `name` //Optional string.// Custom name of this field for the subtype.


The `mutations` key allows you to control the behavior of the "Change Subtype"
action above the comment area. By default, this action allows users to change
the task subtype into any other subtype.

If you'd prefer to make it more difficult to change subtypes or offer only a
subset of subtypes, you can specify the list of subtypes that "Change Subtypes"
offers. For example, if you have several similar subtypes and want to allow
tasks to be converted between them but not easily converted to other types,
you can make the "Change Subtypes" control show only these options like this:

```
  {
    ...
    "mutations": ["bug", "issue", "defect"]
    ...
  }
```

If you specify an empty list, the "Change Subtypes" action will be completely
hidden.

This mutation list is advisory and only configures the UI. Tasks may still be
converted across subtypes freely by using the Bulk Editor or API.

EOTEXT
      ,
      $subtype_default_key));

    $priorities_description = $this->deformat(pht(<<<EOTEXT
Allows you to edit or override the default priorities available in Maniphest,
like "High", "Normal" and "Low". The configuration should contain a map of
numeric priority values (where larger numbers correspond to higher priorities)
to priority specifications (see defaults below for examples).

The keys you can define for a priority are:

  - `name` //Required string.// Name of the priority.
  - `keywords` //Required list<string>.// List of unique keywords which identify
    this priority, like "high" or "low". Each priority must have at least one
    keyword and two priorities may not share the same keyword.
  - `short` //Optional string.// Alternate shorter name, used in UIs where
    there is less space available.
  - `color` //Optional string.// Color for this priority, like "red" or
    "blue".
  - `disabled` //Optional bool.// Set to true to prevent users from choosing
    this priority when creating or editing tasks. Existing tasks will not be
    affected, and can be batch edited to a different priority or left to
    eventually die out.

You can choose the default priority for newly created tasks with
"maniphest.default-priority".
EOTEXT
      ));

    $fields_description = $this->deformat(pht(<<<EOTEXT
List of custom fields for Maniphest tasks.

For details on adding custom fields to Maniphest, see [[ %s | %s ]] in the
documentation.
EOTEXT
      ,
      PhabricatorEnv::getDoclink('Configuring Custom Fields'),
      pht('Configuring Custom Fields')));

    return array(
      $this->newOption('maniphest.custom-field-definitions', 'wild', array())
        ->setSummary(pht('Custom Maniphest fields.'))
        ->setDescription($fields_description)
        ->addExample($fields_json, pht('Valid setting')),
      $this->newOption('maniphest.fields', $custom_field_type, $default_fields)
        ->setCustomData(id(new ManiphestTask())->getCustomFieldBaseClass())
        ->setDescription(pht('Select and reorder task fields.')),
      $this->newOption(
        'maniphest.priorities',
        $priority_type,
        $priority_defaults)
        ->setSummary(pht('Configure Maniphest priority names.'))
        ->setDescription($priorities_description),
      $this->newOption('maniphest.statuses', $status_type, $status_defaults)
        ->setSummary(pht('Configure Maniphest task statuses.'))
        ->setDescription($status_description)
        ->addExample($status_example, pht('Minimal Valid Config')),
      $this->newOption('maniphest.default-priority', 'int', 90)
        ->setSummary(pht('Default task priority for create flows.'))
        ->setDescription(
          pht(
            'Choose a default priority for newly created tasks. You can '.
            'review and adjust available priorities by using the '.
            '%s configuration option. The default value (`90`) '.
            'corresponds to the default "Needs Triage" priority.',
            'maniphest.priorities')),
      $this->newOption('maniphest.points', $points_type, array())
        ->setSummary(pht('Configure point values for tasks.'))
        ->setDescription($points_description)
        ->addExample($points_json_1, pht('Points Config'))
        ->addExample($points_json_2, pht('Hours Config')),
      $this->newOption('maniphest.subtypes', $subtype_type, $subtype_default)
        ->setSummary(pht('Define task subtypes.'))
        ->setDescription($subtype_description)
        ->addExample($subtype_example, pht('Simple Subtypes')),
    );
  }

}
