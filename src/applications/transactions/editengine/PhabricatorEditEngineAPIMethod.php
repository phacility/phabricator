<?php

abstract class PhabricatorEditEngineAPIMethod
  extends ConduitAPIMethod {

  abstract public function newEditEngine();

  public function getApplication() {
    $engine = $this->newEditEngine();
    $class = $engine->getEngineApplicationClass();
    return PhabricatorApplication::getByClass($class);
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('ApplicationEditor methods are highly unstable.');
  }

  final protected function defineParamTypes() {
    return array(
      'transactions' => 'list<map<string, wild>>',
      'objectPHID' => 'optional phid',
    );
  }

  final protected function defineReturnType() {
    return 'map<string, wild>';
  }

  final protected function execute(ConduitAPIRequest $request) {
    $engine = $this->newEditEngine()
      ->setViewer($request->getUser());

    return $engine->buildConduitResponse($request);
  }

  final public function getMethodDescription() {
    // TODO: We don't currently have a real viewer in this method.
    $viewer = new PhabricatorUser();

    $engine = $this->newEditEngine()
      ->setViewer($viewer);

    $types = $engine->getAllEditTypes();

    $out = array();

    $out[] = pht(<<<EOTEXT
This is a standard **ApplicationEditor** method which allows you to create and
modify objects by applying transactions.

Each transaction applies one change to the object. For example, to create an
object with a specific title or change the title of an existing object you might
start by building a transaction like this:

```lang=json, name=Example Single Transaction
{
  "type": "title",
  "value": "New Object Title"
}
```

By passing a list of transactions in the `transactions` parameter, you can
apply a sequence of edits. For example, you'll often pass a value like this to
create an object with several field values or apply changes to multiple fields:

```lang=json, name=Example Transaction List
[
  {
    "type": "title",
    "value": "New Object Title"
  },
  {
    "type": "body",
    "value": "New body text for the object."
  },
  {
    "type": "projects.add",
    "value": ["PHID-PROJ-1111", "PHID-PROJ-2222"]
  }
]
```

Exactly which types of edits are available depends on the object you're editing.


Creating Objects
----------------

To create an object, pass a list of `transactions` but leave `objectPHID`
empty. This will create a new object with the initial field values you
specify.


Editing Objects
---------------

To edit an object, pass a list of `transactions` and specify an object to
apply them to with `objectPHID`. This will apply the changes to the object.


Return Type
-----------

WARNING: The structure of the return value from these methods is likely to
change as ApplicationEditor evolves.

Return values look something like this for now:

```lang=json, name=Example Return Value
{
  "object": {
    "phid": "PHID-XXXX-1111"
  },
  "transactions": [
    {
      "phid": "PHID-YYYY-1111",
    },
    {
      "phid": "PHID-YYYY-2222",
    }
  ]
}
```

The `object` key contains information about the object which was created or
edited.

The `transactions` key contains information about the transactions which were
actually applied. For many reasons, the transactions which actually apply may
be greater or fewer in number than the transactions you provided, or may differ
in their nature in other ways.


Edit Types
==========

This API method supports these edit types:
EOTEXT
      );

    $key = pht('Key');
    $summary = pht('Summary');
    $description = pht('Description');
    $head_type = pht('Type');

    $table = array();
    $table[] = "| {$key} | {$summary} |";
    $table[] = '|--------|----------------|';
    foreach ($types as $type) {
      $edit_type = $type->getEditType();
      $edit_summary = $type->getSummary();
      $table[] = "| `{$edit_type}` | {$edit_summary} |";
    }

    $out[] = implode("\n", $table);

    foreach ($types as $type) {
      $section = array();
      $section[] = pht('Edit Type: %s', $type->getEditType());
      $section[] = '---------';
      $section[] = null;
      $section[] = $type->getDescription();
      $section[] = null;
      $section[] = pht(
        'This edit generates transactions of type `%s` internally.',
        $type->getTransactionType());
      $section[] = null;

      $type_description = pht(
        'Use `%s` to select this edit type.',
        $type->getEditType());

      $value_type = $type->getValueType();
      $value_description = $type->getValueDescription();

      $table = array();
      $table[] = "| {$key} | {$head_type} | {$description} |";
      $table[] = '|--------|--------------|----------------|';
      $table[] = "| `type` | `const` | {$type_description} |";
      $table[] = "| `value` | `{$value_type}` | {$value_description} |";
      $section[] = implode("\n", $table);

      $out[] = implode("\n", $section);
    }

    $out = implode("\n\n", $out);
    return $out;
  }

}
