<?php

/**
 * Renders the "HTTP Parameters" help page for edit engines.
 *
 * This page has a ton of text and specialized rendering on it, this class
 * just pulls it out of the main @{class:PhabricatorEditEngine}.
 */
final class PhabricatorApplicationEditHTTPParameterHelpView
  extends AphrontView {

  private $object;
  private $fields;

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setFields(array $fields) {
    $this->fields = $fields;
    return $this;
  }

  public function getFields() {
    return $this->fields;
  }

  public function render() {
    $object = $this->getObject();
    $fields = $this->getFields();

    $uri = 'https://your.install.com/application/edit/';

    // Remove fields which do not expose an HTTP parameter type.
    $types = array();
    foreach ($fields as $key => $field) {
      if (!$field->shouldGenerateTransactionsFromSubmit()) {
        unset($fields[$key]);
        continue;
      }

      $type = $field->getHTTPParameterType();
      if ($type === null) {
        unset($fields[$key]);
        continue;
      }

      $types[$type->getTypeName()] = $type;
    }

    $intro = pht(<<<EOTEXT
When creating objects in the web interface, you can use HTTP parameters to
prefill fields in the form. This allows you to quickly create a link to a
form with some of the fields already filled in with default values.

To prefill a form, start by finding the URI for the form you want to prefill.
Do this by navigating to the relevant application, clicking the "Create" button
for the type of object you want to create, and then copying the URI out of your
browser's address bar. It will usually look something like this:

```
%s
```

However, `your.install.com` will be the domain where your copy of this software
is installed, and `application/` will be the URI for an application. Some
applications have multiple forms for creating objects or URIs that look a little
different than this example, so the URI may not look exactly like this.

To prefill the form, add properly encoded HTTP parameters to the URI. You
should end up with something like this:

```
%s?title=Platyplus&body=Ornithopter
```

If the form has `title` and `body` fields of the correct types, visiting this
link will prefill those fields with the values "Platypus" and "Ornithopter"
respectively.

The rest of this document shows which parameters you can add to this form and
how to format them.


Supported Fields
----------------

This form supports these fields:

EOTEXT
      ,
      $uri,
      $uri);

    $rows = array();
    foreach ($fields as $field) {
      $rows[] = array(
        $field->getLabel(),
        head($field->getAllReadValueFromRequestKeys()),
        $field->getHTTPParameterType()->getTypeName(),
        $field->getDescription(),
      );
    }

    $main_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Label'),
          pht('Key'),
          pht('Type'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          null,
          null,
          'wide',
        ));

    $aliases_text = pht(<<<EOTEXT
Aliases
-------

Aliases are alternate recognized keys for a field. For example, a field with
a complex key like `examplePHIDs` might be have a simple version of that key
as an alias, like `example`.

Aliases work just like the primary key when prefilling forms. They make it
easier to remember and use HTTP parameters by providing more natural ways to do
some prefilling.

For example, if a field has `examplePHIDs` as a key but has aliases `example`
and `examples`, these three URIs will all do the same thing:

```
%s?examplePHIDs=...
%s?examples=...
%s?example=...
```

If a URI specifies multiple default values for a field, the value using the
primary key has precedence. Generally, you can not mix different aliases in
a single URI.

EOTEXT
      ,
      $uri,
      $uri,
      $uri);

    $rows = array();
    foreach ($fields as $field) {
      $aliases = array_slice($field->getAllReadValueFromRequestKeys(), 1);
      if (!$aliases) {
        continue;
      }
      $rows[] = array(
        $field->getLabel(),
        $field->getKey(),
        implode(', ', $aliases),
      );
    }

    $alias_table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('This object has no fields with aliases.'))
      ->setHeaders(
        array(
          pht('Label'),
          pht('Key'),
          pht('Aliases'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          null,
          'wide',
        ));

    $template_text = pht(<<<EOTEXT
Template Objects
----------------

Instead of specifying each field value individually, you can specify another
object to use as a template. Some of the initial fields will be copied from the
template object.

Specify a template object with the `template` parameter. You can use an ID,
PHID, or monogram (for objects which have monograms). For example, you might
use URIs like these:

```
%s?template=123
%s?template=PHID-WXYZ-abcdef...
%s?template=T123
```

You can combine the `template` parameter with HTTP parameters: the template
object will be copied first, then any HTTP parameters will be read.

When using `template`, these fields will be copied:
EOTEXT
      ,
      $uri,
      $uri,
      $uri);

    $yes = id(new PHUIIconView())->setIcon('fa-check-circle green');
    $no = id(new PHUIIconView())->setIcon('fa-times grey');

    $rows = array();
    foreach ($fields as $field) {
      $rows[] = array(
        $field->getLabel(),
        $field->getIsCopyable() ? $yes : $no,
      );
    }

    $template_table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht('None of the fields on this object support templating.'))
      ->setHeaders(
        array(
          pht('Field'),
          pht('Will Copy'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          'wide',
        ));

    $select_text = pht(<<<EOTEXT
Select Fields
-------------

Some fields support selection from a specific set of values. When prefilling
these fields, use the value in the **Value** column to select the appropriate
setting.

EOTEXT
      );

    $rows = array();
    foreach ($fields as $field) {
      if (!($field instanceof PhabricatorSelectEditField)) {
        continue;
      }

      $options = $field->getOptions();
      $label = $field->getLabel();
      foreach ($options as $option_key => $option_value) {
        if (strlen($option_key)) {
          $option_display = $option_key;
        } else {
          $option_display = phutil_tag('em', array(), pht('<empty>'));
        }

        $rows[] = array(
          $label,
          $option_display,
          $option_value,
        );
        $label = null;
      }
    }

    $select_table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('This object has no select fields.'))
      ->setHeaders(
        array(
          pht('Field'),
          pht('Value'),
          pht('Label'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          null,
          'wide',
        ));

    $types_text = pht(<<<EOTEXT
Field Types
-----------

Fields in this form have the types described in the table below. This table
shows how to format values for each field type.
EOTEXT
      );

    $types_table = id(new PhabricatorHTTPParameterTypeTableView())
      ->setHTTPParameterTypes($types);

    return array(
      $this->renderInstructions($intro),
      $main_table,
      $this->renderInstructions($aliases_text),
      $alias_table,
      $this->renderInstructions($template_text),
      $template_table,
      $this->renderInstructions($select_text),
      $select_table,
      $this->renderInstructions($types_text),
      $types_table,
    );
  }

  protected function renderInstructions($corpus) {
    $viewer = $this->getUser();
    $view = new PHUIRemarkupView($viewer, $corpus);

    $view->setRemarkupOptions(
      array(
        PHUIRemarkupView::OPTION_PRESERVE_LINEBREAKS => false,
      ));

    return $view;
  }

}
