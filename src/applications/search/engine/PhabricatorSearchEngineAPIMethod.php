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
      PhabricatorEnv::getDoclink('Conduit API: Using Search Endpoints'));
  }

  final protected function newDocumentationPages(PhabricatorUser $viewer) {
    $viewer = $this->getViewer();

    $engine = $this->newSearchEngine()
      ->setViewer($viewer);

    $query = $engine->newQuery();

    $out = array();

    $out[] = $this->buildQueriesDocumentationPage($viewer, $engine);
    $out[] = $this->buildConstraintsDocumentationPage($viewer, $engine);
    $out[] = $this->buildOrderDocumentationPage($viewer, $engine, $query);
    $out[] = $this->buildFieldsDocumentationPage($viewer, $engine);
    $out[] = $this->buildAttachmentsDocumentationPage($viewer, $engine);
    $out[] = $this->buildPagingDocumentationPage($viewer, $engine);

    return $out;
  }

  private function buildQueriesDocumentationPage(
    PhabricatorUser $viewer,
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

    $title = pht('Prebuilt Queries');
    $content = array(
      $this->newRemarkupDocumentationView($info),
      $table,
    );

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('queries');
  }

  private function buildConstraintsDocumentationPage(
    PhabricatorUser $viewer,
    PhabricatorApplicationSearchEngine $engine) {

    $info = pht(<<<EOTEXT
You can apply custom constraints by passing a dictionary in `constraints`.
This will let you search for specific sets of results (for example, you may
want show only results with a certain state, status, or owner).


If you specify both a `queryKey` and `constraints`, the builtin or saved query
will be applied first as a starting point, then any additional values in
`constraints` will be applied, overwriting the defaults from the original query.

Different endpoints support different constraints. The constraints this method
supports are detailed below. As an example, you might specify constraints like
this:

```lang=json, name="Example Custom Constraints"
{
  ...
  "constraints": {
    "authorPHIDs": ["PHID-USER-1111", "PHID-USER-2222"],
    "flavors": ["cherry", "orange"],
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

    $constant_lists = array();

    $rows = array();
    foreach ($fields as $field) {
      $key = $field->getConduitKey();
      $label = $field->getLabel();

      $constants = $field->newConduitConstants();
      $show_table = false;

      $type_object = $field->getConduitParameterType();
      if ($type_object) {
        $type = $type_object->getTypeName();
        $description = $field->getDescription();
        if ($constants) {
          $description = array(
            $description,
            ' ',
            phutil_tag('em', array(), pht('(See table below.)')),
          );
          $show_table = true;
        }
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

      if ($show_table) {
        $constant_lists[] = $this->newRemarkupDocumentationView(
          pht(
            'Constants supported by the `%s` constraint:',
            $key));

        $constants_rows = array();
        foreach ($constants as $constant) {
          if ($constant->getIsDeprecated()) {
            $icon = id(new PHUIIconView())
              ->setIcon('fa-exclamation-triangle', 'red');
          } else {
            $icon = null;
          }

          $constants_rows[] = array(
            $constant->getKey(),
            array(
              $icon,
              ' ',
              $constant->getValue(),
            ),
          );
        }

        $constants_table = id(new AphrontTableView($constants_rows))
          ->setHeaders(
            array(
              pht('Key'),
              pht('Value'),
            ))
          ->setColumnClasses(
            array(
              'mono',
              'wide',
            ));

        $constant_lists[] = $constants_table;
      }
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


    $title = pht('Constraints');
    $content = array(
      $this->newRemarkupDocumentationView($info),
      $table,
      $constant_lists,
    );

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('constraints')
      ->setIconIcon('fa-filter');
  }

  private function buildOrderDocumentationPage(
    PhabricatorUser $viewer,
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

    $title = pht('Result Ordering');
    $content = array(
      $this->newRemarkupDocumentationView($orders_info),
      $orders_table,
      $this->newRemarkupDocumentationView($columns_info),
      $columns_table,
    );

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('ordering')
      ->setIconIcon('fa-sort-numeric-asc');
  }

  private function buildFieldsDocumentationPage(
    PhabricatorUser $viewer,
    PhabricatorApplicationSearchEngine $engine) {

    $info = pht(<<<EOTEXT
Objects matching your query are returned as a list of dictionaries in the
`data` property of the results. Each dictionary has some metadata and a
`fields` key, which contains the information about the object that most callers
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

    $title = pht('Object Fields');
    $content = array(
      $this->newRemarkupDocumentationView($info),
      $table,
    );

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('fields')
      ->setIconIcon('fa-cube');
  }

  private function buildAttachmentsDocumentationPage(
    PhabricatorUser $viewer,
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
      ->setNoDataString(pht('This call does not support any attachments.'))
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

    $title = pht('Attachments');
    $content = array(
      $this->newRemarkupDocumentationView($info),
      $table,
    );

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('attachments')
      ->setIconIcon('fa-cubes');
  }

  private function buildPagingDocumentationPage(
    PhabricatorUser $viewer,
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

    $title = pht('Paging and Limits');
    $content = array(
      $this->newRemarkupDocumentationView($info),
    );

    return $this->newDocumentationBoxPage($viewer, $title, $content)
      ->setAnchor('paging')
      ->setIconIcon('fa-clone');
  }

}
