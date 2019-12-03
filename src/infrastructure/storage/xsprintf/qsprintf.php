<?php

/**
 * Format an SQL query. This function behaves like `sprintf`, except that all
 * the normal conversions (like "%s") will be properly escaped, and additional
 * conversions are supported:
 *
 *   %nd, %ns, %nf, %nB
 *     "Nullable" versions of %d, %s, %f and %B. Will produce 'NULL' if the
 *     argument is a strict null.
 *
 *   %=d, %=s, %=f
 *     "Nullable Test" versions of %d, %s and %f. If you pass a value, you
 *     get "= 3"; if you pass null, you get "IS NULL". For instance, this
 *     will work properly if `hatID' is a nullable column and $hat is null.
 *
 *       qsprintf($escaper, 'WHERE hatID %=d', $hat);
 *
 *   %Ld, %Ls, %Lf, %LB
 *     "List" versions of %d, %s, %f and %B. These are appropriate for use in
 *     an "IN" clause. For example:
 *
 *       qsprintf($escaper, 'WHERE hatID IN (%Ld)', $list_of_hats);
 *
 *   %B ("Binary String")
 *     Escapes a string for insertion into a pure binary column, ignoring
 *     tests for characters outside of the basic multilingual plane.
 *
 *   %C, %LC, %LK ("Column", "Key Column")
 *     Escapes a column name or a list of column names. The "%LK" variant
 *     escapes a list of key column specifications which may look like
 *     "column(32)".
 *
 *   %K ("Comment")
 *     Escapes a comment.
 *
 *   %Q, %LA, %LO, %LQ, %LJ ("Query Fragment")
 *     Injects a query fragment from a prior call to qsprintf(). The list
 *     variants join a list of query fragments with AND, OR, comma, or space.
 *
 *   %Z ("Raw Query")
 *     Injects a raw, unescaped query fragment. Dangerous!
 *
 *   %R ("Database and Table Reference")
 *     Behaves like "%T.%T" and prints a full reference to a table including
 *     the database. Accepts a AphrontDatabaseTableRefInterface.
 *
 *   %P ("Password or Secret")
 *     Behaves like "%s", but shows "********" when the query is printed in
 *     logs or traces. Accepts a PhutilOpaqueEnvelope.
 *
 *   %~ ("Substring")
 *     Escapes a substring query for a LIKE (or NOT LIKE) clause. For example:
 *
 *       //  Find all rows with $search as a substring of `name`.
 *       qsprintf($escaper, 'WHERE name LIKE %~', $search);
 *
 *     See also %> and %<.
 *
 *   %> ("Prefix")
 *     Escapes a prefix query for a LIKE clause. For example:
 *
 *       //  Find all rows where `name` starts with $prefix.
 *       qsprintf($escaper, 'WHERE name LIKE %>', $prefix);
 *
 *   %< ("Suffix")
 *     Escapes a suffix query for a LIKE clause. For example:
 *
 *       //  Find all rows where `name` ends with $suffix.
 *       qsprintf($escaper, 'WHERE name LIKE %<', $suffix);
 *
 *   %T ("Table")
 *     Escapes a table name. In most cases, you should use "%R" instead.
 */
function qsprintf(PhutilQsprintfInterface $escaper, $pattern /* , ... */) {
  $args = func_get_args();
  array_shift($args);
  return new PhutilQueryString($escaper, $args);
}

function vqsprintf(PhutilQsprintfInterface $escaper, $pattern, array $argv) {
  array_unshift($argv, $pattern);
  return new PhutilQueryString($escaper, $argv);
}

/**
 * @{function:xsprintf} callback for encoding SQL queries. See
 * @{function:qsprintf}.
 */
function xsprintf_query($userdata, &$pattern, &$pos, &$value, &$length) {
  $type = $pattern[$pos];

  if (is_array($userdata)) {
    $escaper = $userdata['escaper'];
    $unmasked = $userdata['unmasked'];
  } else {
    $escaper = $userdata;
    $unmasked = false;
  }

  $next = (strlen($pattern) > $pos + 1) ? $pattern[$pos + 1] : null;
  $nullable = false;
  $done = false;

  $prefix   = '';

  if (!($escaper instanceof PhutilQsprintfInterface)) {
    throw new InvalidArgumentException(pht('Invalid database escaper.'));
  }

  switch ($type) {
    case '=': // Nullable test
      switch ($next) {
        case 'd':
        case 'f':
        case 's':
          $pattern = substr_replace($pattern, '', $pos, 1);
          $length  = strlen($pattern);
          $type    = 's';
          if ($value === null) {
            $value = 'IS NULL';
            $done = true;
          } else {
            $prefix = '= ';
            $type = $next;
          }
          break;
        default:
          throw new Exception(
            pht(
              'Unknown conversion, try %s, %s, or %s.',
              '%=d',
              '%=s',
              '%=f'));
      }
      break;

    case 'n': // Nullable...
      switch ($next) {
        case 'd': //  ...integer.
        case 'f': //  ...float.
        case 's': //  ...string.
        case 'B': //  ...binary string.
          $pattern = substr_replace($pattern, '', $pos, 1);
          $length = strlen($pattern);
          $type = $next;
          $nullable = true;
          break;
        default:
          throw new XsprintfUnknownConversionException("%n{$next}");
      }
      break;

    case 'L': // List of..
      qsprintf_check_type($value, "L{$next}", $pattern);
      $pattern = substr_replace($pattern, '', $pos, 1);
      $length  = strlen($pattern);
      $type = 's';
      $done = true;

      switch ($next) {
        case 'd': //  ...integers.
          $value = implode(', ', array_map('intval', $value));
          break;
        case 'f': // ...floats.
          $value = implode(', ', array_map('floatval', $value));
          break;
        case 's': // ...strings.
          foreach ($value as $k => $v) {
            $value[$k] = "'".$escaper->escapeUTF8String((string)$v)."'";
          }
          $value = implode(', ', $value);
          break;
        case 'B': // ...binary strings.
          foreach ($value as $k => $v) {
            $value[$k] = "'".$escaper->escapeBinaryString((string)$v)."'";
          }
          $value = implode(', ', $value);
          break;
        case 'C': // ...columns.
          foreach ($value as $k => $v) {
            $value[$k] = $escaper->escapeColumnName($v);
          }
          $value = implode(', ', $value);
          break;
        case 'K': // ...key columns.
          // This is like "%LC", but for escaping column lists passed to key
          // specifications. These should be escaped as "`column`(123)". For
          // example:
          //
          //   ALTER TABLE `x` ADD KEY `y` (`u`(16), `v`(32));

          foreach ($value as $k => $v) {
            $matches = null;
            if (preg_match('/\((\d+)\)\z/', $v, $matches)) {
              $v = substr($v, 0, -(strlen($matches[1]) + 2));
              $prefix_len = '('.((int)$matches[1]).')';
            } else {
              $prefix_len = '';
            }

            $value[$k] = $escaper->escapeColumnName($v).$prefix_len;
          }

          $value = implode(', ', $value);
          break;
        case 'Q':
          // TODO: Here, and in "%LO", "%LA", and "%LJ", we should eventually
          // stop accepting strings.
          foreach ($value as $k => $v) {
            if (is_string($v)) {
              continue;
            }
            $value[$k] = $v->getUnmaskedString();
          }
          $value = implode(', ', $value);
          break;
        case 'O':
          foreach ($value as $k => $v) {
            if (is_string($v)) {
              continue;
            }
            $value[$k] = $v->getUnmaskedString();
          }
          if (count($value) == 1) {
            $value = '('.head($value).')';
          } else {
            $value = '(('.implode(') OR (', $value).'))';
          }
          break;
        case 'A':
          foreach ($value as $k => $v) {
            if (is_string($v)) {
              continue;
            }
            $value[$k] = $v->getUnmaskedString();
          }
          if (count($value) == 1) {
            $value = '('.head($value).')';
          } else {
            $value = '(('.implode(') AND (', $value).'))';
          }
          break;
        case 'J':
          foreach ($value as $k => $v) {
            if (is_string($v)) {
              continue;
            }
            $value[$k] = $v->getUnmaskedString();
          }
          $value = implode(' ', $value);
          break;
        default:
          throw new XsprintfUnknownConversionException("%L{$next}");
      }
      break;
  }

  if (!$done) {
    qsprintf_check_type($value, $type, $pattern);
    switch ($type) {
      case 's': // String
        if ($nullable && $value === null) {
          $value = 'NULL';
        } else {
          $value = "'".$escaper->escapeUTF8String((string)$value)."'";
        }
        $type = 's';
        break;

      case 'B': // Binary String
        if ($nullable && $value === null) {
          $value = 'NULL';
        } else {
          $value = "'".$escaper->escapeBinaryString((string)$value)."'";
        }
        $type = 's';
        break;

      case 'Q': // Query Fragment
        if ($value instanceof PhutilQueryString) {
          $value = $value->getUnmaskedString();
        }
        $type = 's';
        break;

      case 'Z': // Raw Query Fragment
        $type = 's';
        break;

      case '~': // Like Substring
      case '>': // Like Prefix
      case '<': // Like Suffix
        $value = $escaper->escapeStringForLikeClause($value);
        switch ($type) {
          case '~': $value = "'%".$value."%'"; break;
          case '>': $value = "'".$value."%'"; break;
          case '<': $value = "'%".$value."'"; break;
        }
        $type  = 's';
        break;

      case 'f': // Float
        if ($nullable && $value === null) {
          $value = 'NULL';
        } else {
          $value = (float)$value;
        }
        $type = 's';
        break;

      case 'd': // Integer
        if ($nullable && $value === null) {
          $value = 'NULL';
        } else {
          $value = (int)$value;
        }
        $type = 's';
        break;

      case 'T': // Table
      case 'C': // Column
        $value = $escaper->escapeColumnName($value);
        $type = 's';
        break;

      case 'K': // Komment
        $value = $escaper->escapeMultilineComment($value);
        $type = 's';
        break;

      case 'R': // Database + Table Reference
        $database_name = $value->getAphrontRefDatabaseName();
        $database_name = $escaper->escapeColumnName($database_name);

        $table_name = $value->getAphrontRefTableName();
        $table_name = $escaper->escapeColumnName($table_name);

        $value = $database_name.'.'.$table_name;
        $type = 's';
        break;

      case 'P': // Password or Secret
        if ($unmasked) {
          $value = $value->openEnvelope();
          $value = "'".$escaper->escapeUTF8String($value)."'";
        } else {
          $value = '********';
        }
        $type = 's';
        break;

      default:
        throw new XsprintfUnknownConversionException($type);
    }
  }

  if ($prefix) {
    $value = $prefix.$value;
  }

  $pattern[$pos] = $type;
}

function qsprintf_check_type($value, $type, $query) {
  switch ($type) {
    case 'Ld':
    case 'Ls':
    case 'LC':
    case 'LK':
    case 'LB':
    case 'Lf':
    case 'LQ':
    case 'LA':
    case 'LO':
    case 'LJ':
      if (!is_array($value)) {
        throw new AphrontParameterQueryException(
          $query,
          pht('Expected array argument for %%%s conversion.', $type));
      }
      if (empty($value)) {
        throw new AphrontParameterQueryException(
          $query,
          pht('Array for %%%s conversion is empty.', $type));
      }

      foreach ($value as $scalar) {
        qsprintf_check_scalar_type($scalar, $type, $query);
      }
      break;
    default:
      qsprintf_check_scalar_type($value, $type, $query);
      break;
  }
}

function qsprintf_check_scalar_type($value, $type, $query) {
  switch ($type) {
    case 'LQ':
    case 'LA':
    case 'LO':
    case 'LJ':
      // TODO: See T13217. Remove this eventually.
      if (is_string($value)) {
        phlog(
          pht(
            'UNSAFE: Raw string ("%s") passed to query ("%s") subclause '.
            'for "%%%s" conversion. Subclause conversions should be passed '.
            'a list of PhutilQueryString objects.',
            $value,
            $query,
            $type));
        break;
      }

      if (!($value instanceof PhutilQueryString)) {
        throw new AphrontParameterQueryException(
          $query,
          pht(
            'Expected a list of PhutilQueryString objects for %%%s '.
            'conversion.',
            $type));
      }
      break;

    case 'Q':
      // TODO: See T13217. Remove this eventually.
      if (is_string($value)) {
        phlog(
          pht(
            'UNSAFE: Raw string ("%s") passed to query ("%s") for "%%Q" '.
            'conversion. %%Q should be passed a query string.',
            $value,
            $query));
        break;
      }

      if (!($value instanceof PhutilQueryString)) {
        throw new AphrontParameterQueryException(
          $query,
          pht('Expected a PhutilQueryString for %%%s conversion.', $type));
      }
      break;

    case 'Z':
      if (!is_string($value)) {
        throw new AphrontParameterQueryException(
          $query,
          pht('Value for "%%Z" conversion should be a raw string.'));
      }
      break;

    case 'LC':
    case 'LK':
    case 'T':
    case 'C':
      if (!is_string($value)) {
        throw new AphrontParameterQueryException(
          $query,
          pht('Expected a string for %%%s conversion.', $type));
      }
      break;

    case 'Ld':
    case 'Lf':
    case 'd':
    case 'f':
      if (!is_null($value) && !is_numeric($value)) {
        throw new AphrontParameterQueryException(
          $query,
          pht('Expected a numeric scalar or null for %%%s conversion.', $type));
      }
      break;

    case 'Ls':
    case 's':
    case 'LB':
    case 'B':
    case '~':
    case '>':
    case '<':
    case 'K':
      if (!is_null($value) && !is_scalar($value)) {
        throw new AphrontParameterQueryException(
          $query,
          pht('Expected a scalar or null for %%%s conversion.', $type));
      }
      break;

    case 'R':
      if (!($value instanceof AphrontDatabaseTableRefInterface)) {
        throw new AphrontParameterQueryException(
          $query,
          pht(
            'Parameter to "%s" conversion in "qsprintf(...)" is not an '.
            'instance of AphrontDatabaseTableRefInterface.',
            '%R'));
      }
      break;

    case 'P':
      if (!($value instanceof PhutilOpaqueEnvelope)) {
        throw new AphrontParameterQueryException(
          $query,
          pht(
            'Parameter to "%s" conversion in "qsprintf(...)" is not an '.
            'instance of PhutilOpaqueEnvelope.',
            '%P'));
      }
      break;

    default:
      throw new XsprintfUnknownConversionException($type);
  }
}
