<?php

abstract class PhabricatorSearchEngineAPIMethod
  extends ConduitAPIMethod {

  abstract public function newSearchEngine();

  public function getApplication() {
    $engine = $this->newSearchEngine();
    $class = $engine->getApplicationClassName();
    return PhabricatorApplication::getByClass($class);
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('ApplicationSearch methods are highly unstable.');
  }

  final protected function defineParamTypes() {
    return array(
      'queryKey' => 'optional string',
      'constraints' => 'optional map<string, wild>',
      'order' => 'optional order',
    ) + $this->getPagerParamTypes();
  }

  final protected function defineReturnType() {
    return 'map<string, wild>';
  }

  final protected function execute(ConduitAPIRequest $request) {
    $engine = $this->newSearchEngine()
      ->setViewer($request->getUser());

    return $engine->buildConduitResponse($request);
  }

  final public function getMethodDescription() {
    // TODO: We don't currently have a real viewer in this method.
    $viewer = PhabricatorUser::getOmnipotentUser();

    $engine = $this->newSearchEngine()
      ->setViewer($viewer);

    $query = $engine->newQuery();

    $out = array();

    $out[] = pht(<<<EOTEXT
This is a standard **ApplicationSearch** method which will let you list, query,
or search for objects.

EOTEXT
      );

    $out[] = pht(<<<EOTEXT
Prebuilt Queries
----------------

You can use a builtin or saved query as a starting point by passing it with
`queryKey`. If you don't specify a `queryKey`, the query will start with no
constraints.

For example, many applications have builtin queries like `"active"` or
`"open"` to find only active or enabled results. To use a `queryKey`, specify
it like this:

```lang=json
{
  ...
  "queryKey": "active",
  ...
}
```

These builtin and saved queries are available:
EOTEXT
      );

    $head_querykey = pht('Query Key');
    $head_name = pht('Name');
    $head_builtin = pht('Builtin');

    $named_queries = $engine->loadAllNamedQueries();

    $table = array();
    $table[] = "| {$head_querykey} | {$head_name} | {$head_builtin} |";
    $table[] = '|------------------|--------------|-----------------|';
    foreach ($named_queries as $named_query) {
      $key = $named_query->getQueryKey();
      $name = $named_query->getQueryName();
      $builtin = $named_query->getIsBuiltin()
        ? pht('Builtin')
        : pht('Custom');

      $table[] = "| `{$key}` | {$name} | {$builtin} |";
    }
    $table = implode("\n", $table);
    $out[] = $table;

    $out[] = pht(<<<EOTEXT
You can also use **any** query you run via the web UI as a starting point. You
can find the key for a query by examining the URI after running a normal
search.
EOTEXT
      );

    $out[] = pht(<<<EOTEXT
Custom Constraints
------------------

You can add custom constraints to the basic query by passing `constraints`.
This will let you filter results (for example, show only results with a
certain state, status, or owner).

Specify constraints like this:

```lang=json
{
  ...
  "constraints": {
    "authorPHIDs": ["PHID-USER-1111", "PHID-USER-2222"],
    "statuses": ["open", "closed"]
  },
  ...
}
```

If you specify both a `queryKey` and `constraints`, the basic query
configuration will be applied first as a starting point, then any additional
values in `constraints` will be applied, overwriting the defaults from the
original query.

This API endpoint supports these constraints:
EOTEXT
      );

    $head_key = pht('Key');
    $head_label = pht('Label');
    $head_type = pht('Type');
    $head_desc = pht('Description');

    $fields = $engine->getSearchFieldsForConduit();

    $table = array();
    $table[] = "| {$head_key} | {$head_label} | {$head_type} | {$head_desc} |";
    $table[] = '|-------------|---------------|--------------|--------------|';
    foreach ($fields as $field) {
      $key = $field->getKey();
      $label = $field->getLabel();

      // TODO: Support generating and surfacing this information.
      $type = pht('TODO');
      $description = pht('TODO');

      $table[] = "| `{$key}` | **{$label}** | `{$type}` | {$description}";
    }
    $table = implode("\n", $table);
    $out[] = $table;


    $out[] = pht(<<<EOTEXT
Result Order
------------

Use `order` to choose an ordering for the results. Either specify a single
key from the builtin orders (these are a set of meaningful, high-level,
human-readable orders) or specify a list of low-level columns.

To use a high-level order, choose a builtin order from the table below
and specify it like this:

```lang=json
{
  ...
  "order": "newest",
  ...
}
```

These builtin orders are available:
EOTEXT
      );

    $head_builtin = pht('Builtin Order');
    $head_description = pht('Description');
    $head_columns = pht('Columns');

    $orders = $query->getBuiltinOrders();

    $table = array();
    $table[] = "| {$head_builtin} | {$head_description} | {$head_columns} |";
    $table[] = '|-----------------|---------------------|-----------------|';
    foreach ($orders as $key => $order) {
      $name = $order['name'];
      $columns = implode(', ', $order['vector']);
      $table[] = "| `{$key}` | {$name} | {$columns} |";
    }
    $table = implode("\n", $table);
    $out[] = $table;

    $out[] = pht(<<<EOTEXT
You can choose a low-level column order instead. This is an advanced feature.

In your custom order: each column may only be specified once; each column may
be prefixed with "-" to invert the order; the last column must be unique; and
no column other than the last may be unique.

To use a low-level order, choose a sequence of columns and specify them like
this:

```lang=json
{
  ...
  "order": ["color", "-name", "id"],
  ...
}
```

These low-level columns are available:
EOTEXT
      );

    $head_column = pht('Column Key');
    $head_unique = pht('Unique');

    $columns = $query->getOrderableColumns();

    $table = array();
    $table[] = "| {$head_column} | {$head_unique} |";
    $table[] = '|----------------|----------------|';
    foreach ($columns as $key => $column) {
      $unique = idx($column, 'unique')
        ? pht('Yes')
        : pht('No');

      $table[] = "| `{$key}` | {$unique} |";
    }
    $table = implode("\n", $table);
    $out[] = $table;


    $out[] = pht(<<<EOTEXT
Result Format
-------------

The result format is a dictionary with several fields:

  - `data`: Contains the actual results, as a list of dictionaries.
  - `query`: Details about the query which was issued.
  - `cursor`: Information about how to issue another query to get the next
    (or previous) page of results. See "Paging and Limits" below.

EOTEXT
      );

    $out[] = pht(<<<EOTEXT
Fields
------

The `data` field of the result contains a list of results. Each result has
some metadata and a `fields` key, which contains the primary object fields.

For example, the results may look something like this:

```lang=json
{
  ...
  "data": [
    {
      "id": 123,
      "phid": "PHID-WXYZ-1111",
      "fields": {
        "name": "First Example Object",
        "authorPHID": "PHID-USER-2222"
      }
    },
    {
      "id": 124,
      "phid": "PHID-WXYZ-3333",
      "fields": {
        "name": "Second Example Object",
        "authorPHID": "PHID-USER-4444"
      }
    },
    ...
  ]
  ...
}
```

This result structure is standardized across all search methods, but the
available fields differ from application to application.

These are the fields available on this object type:

EOTEXT
      );

    $specs = $engine->getAllConduitFieldSpecifications();

    $table = array();
    $table[] = "| {$head_key} | {$head_type} | {$head_description} |";
    $table[] = '|-------------|--------------|---------------------|';
    foreach ($specs as $key => $spec) {
      $type = idx($spec, 'type');
      $description = idx($spec, 'description');
      $table[] = "| `{$key}` | `{$type}` | {$description} |";
    }
    $table = implode("\n", $table);
    $out[] = $table;

    $out[] = pht(<<<EOTEXT
Paging and Limits
-----------------

Queries are limited to returning 100 results at a time. If you want fewer
results than this, you can use `limit` to specify a smaller limit.

If you want more results, you'll need to make additional queries to retrieve
more pages of results.

The result structure contains a `cursor` key with information you'll need in
order to fetch the next page. After an initial query, it will usually look
something like this:

```lang=json
{
  ...
  "cursor": {
    "limit": 100,
    "after": "1234",
    "before": null,
    "order": null
  }
  ...
}
```

The `limit` and `order` fields are describing the effective limit and order the
query was executed with, and are usually not of much interest. The `after` and
`before` fields give you cursors which you can pass when making another API
call in order to get the next (or previous) page of results.

To get the next page of results, repeat your API call with all the same
parameters as the original call, but pass the `after` cursor you received from
the first call in the `after` parameter when making the second call.

If you do things correctly, you should get the second page of results, and
a cursor structure like this:

```lang=json
{
  ...
  "cursor": {
    "limit": 5,
    "after": "4567",
    "before": "7890",
    "order": null
  }
  ...
}
```

You can now continue to the third page of results by passing the new `after`
cursor to the `after` parameter in your third call, or return to the previous
page of results by passing the `before` cursor to the `before` parameter. This
might be useful if you are rendering a web UI for a user and want to provide
"Next Page" and "Previous Page" links.

If `after` is `null`, there is no next page of results available. Likewise,
if `before` is `null`, there are no previous results available.

EOTEXT
      );

    $out = implode("\n\n", $out);
    return $out;
  }

}
