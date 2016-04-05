<?php

abstract class PhabricatorSearchEngineAPIMethod
  extends ConduitAPIMethod {

  abstract public function newSearchEngine();

  final public function getQueryMaps($query) {
    $maps = $this->getCustomQueryMaps($query);

    // Make sure we emit empty maps as objects, not lists.
    foreach ($maps as $key => $map) {
      if (!$map) {
        $maps[$key] = (object)$map;
      }
    }

    if (!$maps) {
      $maps = (object)$maps;
    }

    return $maps;
  }

  protected function getCustomQueryMaps($query) {
    return array();
  }

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
      'attachments' => 'optional map<string, bool>',
      'order' => 'optional order',
    ) + $this->getPagerParamTypes();
  }

  final protected function defineReturnType() {
    return 'map<string, wild>';
  }

  final protected function execute(ConduitAPIRequest $request) {
    $engine = $this->newSearchEngine()
      ->setViewer($request->getUser());

    return $engine->buildConduitResponse($request, $this);
  }

  final public function getMethodDescription() {
    return pht(
      'This is a standard **ApplicationSearch** method which will let you '.
      'list, query, or search for objects. For documentation on these '.
      'endpoints, see **[[ %s | Conduit API: Using Search Endpoints ]]**.',
      PhabricatorEnv::getDoclink('Conduit API: Using Edit Endpoints'));
  }

  final public function getMethodDocumentation() {
    $viewer = $this->getViewer();

    $engine = $this->newSearchEngine()
      ->setViewer($viewer);

    $query = $engine->newQuery();

    $out = array();

    $out[] = $this->buildQueriesBox($engine);
    $out[] = $this->buildConstraintsBox($engine);
    $out[] = $this->buildOrderBox($engine, $query);
    $out[] = $this->buildFieldsBox($engine);
    $out[] = $this->buildAttachmentsBox($engine);
    $out[] = $this->buildPagingBox($engine);

    return $out;
  }

  private function buildQueriesBox(
    PhabricatorApplicationSearchEngine $engine) {
    $viewer = $this->getViewer();

    $info = pht(<<<EOTEXT
You can choose a builtin or saved query as a starting point for filtering
results by selecting it with `queryKey`. If you don't specify a `queryKey`,
the query will start with no constraints.

For example, many applications have builtin queries like `"active"` or
`"open"` to find only active or enabled results. To use a `queryKey`, specify
it like this:

```lang=json, name="Selecting a Builtin Query"
{
  ...
  "queryKey": "active",
  ...
}
```

The table below shows the keys to use to select builtin queries and your
saved queries, but you can also use **any** query you run via the web UI as a
starting point. You can find the key for a query by examining the URI after
running a normal search.

You can use these keys to select builtin queries and your configured saved
queries:
EOTEXT
      );

    $named_queries = $engine->loadAllNamedQueries();

    $rows = array();
    foreach ($named_queries as $named_query) {
      $builtin = $named_query->getIsBuiltin()
        ? pht('Builtin')
        : pht('Custom');

      $rows[] = array(
        $named_query->getQueryKey(),
        $named_query->getQueryName(),
        $builtin,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Query Key'),
          pht('Name'),
          pht('Builtin'),
        ))
      ->setColumnClasses(
        array(
          'prewrap',
          'pri wide',
          null,
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Builtin and Saved Queries'))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($info))
      ->appendChild($table);
  }

  private function buildConstraintsBox(
    PhabricatorApplicationSearchEngine $engine) {

    $info = pht(<<<EOTEXT
You can apply custom constraints by passing a dictionary in `constraints`.
This will let you search for specific sets of results (for example, you may
want show only results with a certain state, status, or owner).


If you specify both a `queryKey` and `constraints`, the builtin or saved query
will be applied first as a starting point, then any additional values in
`constraints` will be applied, overwriting the defaults from the original query.

Specify constraints like this:

```lang=json, name="Example Custom Constraints"
{
  ...
  "constraints": {
    "authors": ["PHID-USER-1111", "PHID-USER-2222"],
    "statuses": ["open", "closed"],
    ...
  },
  ...
}
```

This API endpoint supports these constraints:
EOTEXT
      );

    $fields = $engine->getSearchFieldsForConduit();

    // As a convenience, put these fields at the very top, even if the engine
    // specifies and alternate display order for the web UI. These fields are
    // very important in the API and nearly useless in the web UI.
    $fields = array_select_keys(
      $fields,
      array('ids', 'phids')) + $fields;

    $rows = array();
    foreach ($fields as $field) {
      $key = $field->getConduitKey();
      $label = $field->getLabel();

      $type_object = $field->getConduitParameterType();
      if ($type_object) {
        $type = $type_object->getTypeName();
        $description = $field->getDescription();
      } else {
        $type = null;
        $description = phutil_tag('em', array(), pht('Not supported.'));
      }

      $rows[] = array(
        $key,
        $label,
        $type,
        $description,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Label'),
          pht('Type'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          'prewrap',
          'pri',
          'prewrap',
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Custom Query Constraints'))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($info))
      ->appendChild($table);
  }

  private function buildOrderBox(
    PhabricatorApplicationSearchEngine $engine,
    $query) {

    $orders_info = pht(<<<EOTEXT
Use `order` to choose an ordering for the results.

Either specify a single key from the builtin orders (these are a set of
meaningful, high-level, human-readable orders) or specify a custom list of
low-level columns.

To use a high-level order, choose a builtin order from the table below
and specify it like this:

```lang=json, name="Choosing a Result Order"
{
  ...
  "order": "newest",
  ...
}
```

These builtin orders are available:
EOTEXT
      );

    $orders = $query->getBuiltinOrders();

    $rows = array();
    foreach ($orders as $key => $order) {
      $rows[] = array(
        $key,
        $order['name'],
        implode(', ', $order['vector']),
      );
    }

    $orders_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Description'),
          pht('Columns'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          '',
          'wide',
        ));

    $columns_info = pht(<<<EOTEXT
You can choose a low-level column order instead. To do this, provide a list
of columns instead of a single key. This is an advanced feature.

In a custom column order:

  - each column may only be specified once;
  - each column may be prefixed with `-` to invert the order;
  - the last column must be a unique column, usually `id`; and
  - no column other than the last may be unique.

To use a low-level order, choose a sequence of columns and specify them like
this:

```lang=json, name="Using a Custom Order"
{
  ...
  "order": ["color", "-name", "id"],
  ...
}
```

These low-level columns are available:
EOTEXT
      );

    $columns = $query->getOrderableColumns();
    $rows = array();
    foreach ($columns as $key => $column) {
      $rows[] = array(
        $key,
        idx($column, 'unique') ? pht('Yes') : pht('No'),
      );
    }

    $columns_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Unique'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          'wide',
        ));


    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Result Ordering'))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($orders_info))
      ->appendChild($orders_table)
      ->appendChild($this->buildRemarkup($columns_info))
      ->appendChild($columns_table);
  }

  private function buildFieldsBox(
    PhabricatorApplicationSearchEngine $engine) {

    $info = pht(<<<EOTEXT
Objects matching your query are returned as a list of dictionaries in the
`data` property of the results. Each dictionary has some metadata and a
`fields` key, which contains the information abou the object that most callers
will be interested in.

For example, the results may look something like this:

```lang=json, name="Example Results"
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

    $rows = array();
    foreach ($specs as $key => $spec) {
      $type = $spec->getType();
      $description = $spec->getDescription();
      $rows[] = array(
        $key,
        $type,
        $description,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Type'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          'mono',
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Object Fields'))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($info))
      ->appendChild($table);
  }

  private function buildAttachmentsBox(
    PhabricatorApplicationSearchEngine $engine) {

    $info = pht(<<<EOTEXT
By default, only basic information about objects is returned. If you want
more extensive information, you can use available `attachments` to get more
information in the results (like subscribers and projects).

Generally, requesting more information means the query executes more slowly
and returns more data (in some cases, much more data). You should normally
request only the data you need.

To request extra data, specify which attachments you want in the `attachments`
parameter:

```lang=json, name="Example Attachments Request"
{
  ...
  "attachments": {
    "subscribers": true
  },
  ...
}
```

This example specifies that results should include information about
subscribers. In the return value, each object will now have this information
filled out in the corresponding `attachments` value:

```lang=json, name="Example Attachments Result"
{
  ...
  "data": [
    {
      ...
      "attachments": {
        "subscribers": {
          "subscriberPHIDs": [
            "PHID-WXYZ-2222",
          ],
          "subscriberCount": 1,
          "viewerIsSubscribed": false
        }
      },
      ...
    },
    ...
  ],
  ...
}
```

These attachments are available:
EOTEXT
      );

    $attachments = $engine->getConduitSearchAttachments();

    $rows = array();
    foreach ($attachments as $key => $attachment) {
      $rows[] = array(
        $key,
        $attachment->getAttachmentName(),
        $attachment->getAttachmentDescription(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Name'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          'prewrap',
          'pri',
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Attachments'))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($info))
      ->appendChild($table);
  }

  private function buildPagingBox(
    PhabricatorApplicationSearchEngine $engine) {

    $info = pht(<<<EOTEXT
Queries are limited to returning 100 results at a time. If you want fewer
results than this, you can use `limit` to specify a smaller limit.

If you want more results, you'll need to make additional queries to retrieve
more pages of results.

The result structure contains a `cursor` key with information you'll need in
order to fetch the next page of results. After an initial query, it will
usually look something like this:

```lang=json, name="Example Cursor Result"
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

```lang=json, name="Second Result Page"
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

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Paging and Limits'))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($info));
  }

  private function buildRemarkup($remarkup) {
    $viewer = $this->getViewer();

    $view = new PHUIRemarkupView($viewer, $remarkup);

    return id(new PHUIBoxView())
      ->appendChild($view)
      ->addPadding(PHUI::PADDING_LARGE);
  }
}
