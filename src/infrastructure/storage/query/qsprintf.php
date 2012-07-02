<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Format an SQL query. This function behaves like sprintf(), except that
 * all the normal conversions (like %s) will be properly escaped, and
 * additional conversions are supported:
 *
 *   %nd, %ns, %nf
 *     "Nullable" versions of %d, %s and %f. Will produce 'NULL' if the
 *     argument is a strict null.
 *
 *   %=d, %=s, %=f
 *     "Nullable Test" versions of %d, %s and %f. If you pass a value, you
 *     get "= 3"; if you pass null, you get "IS NULL". For instance, this
 *     will work properly if `hatID' is a nullable column and $hat is null.
 *
 *       qsprintf($conn, 'WHERE hatID %=d', $hat);
 *
 *   %Ld, %Ls, %Lf
 *     "List" versions of %d, %s and %f. These are appropriate for use in
 *     an "IN" clause. For example:
 *
 *       qsprintf($conn, 'WHERE hatID IN(%Ld)', $list_of_hats);
 *
 *   %T ("Table")
 *     Escapes a table name.
 *
 *   %C, %LC
 *     Escapes a column name or a list of column names.
 *
 *   %K ("Comment")
 *     Escapes a comment.
 *
 *   %Q ("Query Fragment")
 *     Injects a raw query fragment. Extremely dangerous! Not escaped!
 *
 *   %~ ("Substring")
 *     Escapes a substring query for a LIKE (or NOT LIKE) clause. For example:
 *
 *       //  Find all rows with $search as a substing of `name`.
 *       qsprintf($conn, 'WHERE name LIKE %~', $search);
 *
 *     See also %> and %<.
 *
 *   %> ("Prefix")
 *     Escapes a prefix query for a LIKE clause. For example:
 *
 *       //  Find all rows where `name` starts with $prefix.
 *       qsprintf($conn, 'WHERE name LIKE %>', $prefix);
 *
 *   %< ("Suffix")
 *     Escapes a suffix query for a LIKE clause. For example:
 *
 *       //  Find all rows where `name` ends with $suffix.
 *       qsprintf($conn, 'WHERE name LIKE %<', $suffix);
 *
 * @group storage
 */
function qsprintf(AphrontDatabaseConnection $conn, $pattern/*, ... */) {
  $args = func_get_args();
  array_shift($args);
  return xsprintf('xsprintf_query', $conn, $args);
}

/**
 * @group storage
 */
function vqsprintf(AphrontDatabaseConnection $conn, $pattern, array $argv) {
  array_unshift($argv, $pattern);
  return xsprintf('xsprintf_query', $conn, $argv);
}


/**
 * xsprintf() callback for encoding SQL queries. See qsprintf().
 * @group storage
 */
function xsprintf_query($userdata, &$pattern, &$pos, &$value, &$length) {
  $type   = $pattern[$pos];
  $conn   = $userdata;
  $next   = (strlen($pattern) > $pos + 1) ? $pattern[$pos + 1] : null;

  $nullable = false;
  $done     = false;

  $prefix   = '';

  if (!($conn instanceof AphrontDatabaseConnection)) {
    throw new Exception("Invalid database connection!");
  }

  switch ($type) {
    case '=': // Nullable test
      switch ($next) {
        case 'd':
        case 'f':
        case 's':
          $pattern = substr_replace($pattern, '', $pos, 1);
          $length  = strlen($pattern);
          $type  = 's';
          if ($value === null) {
            $value = 'IS NULL';
            $done = true;
          } else {
            $prefix = '= ';
            $type = $next;
          }
          break;
        default:
          throw new Exception('Unknown conversion, try %=d, %=s, or %=f.');
      }
      break;

    case 'n': // Nullable...
      switch ($next) {
        case 'd': //  ...integer.
        case 'f': //  ...float.
        case 's': //  ...string.
          $pattern = substr_replace($pattern, '', $pos, 1);
          $length = strlen($pattern);
          $type = $next;
          $nullable = true;
          break;
        default:
          throw new Exception('Unknown conversion, try %nd or %ns.');
      }
      break;

    case 'L': // List of..
      _qsprintf_check_type($value, "L{$next}", $pattern);
      $pattern = substr_replace($pattern, '', $pos, 1);
      $length  = strlen($pattern);
      $type = 's';
      $done = true;

      switch ($next) {
        case 'd': //  ...integers.
          $value = implode(', ', array_map('intval', $value));
          break;
        case 's': // ...strings.
          foreach ($value as $k => $v) {
            $value[$k] = "'".$conn->escapeString($v)."'";
          }
          $value = implode(', ', $value);
          break;
        case 'C': // ...columns.
          foreach ($value as $k => $v) {
            $value[$k] = $conn->escapeColumnName($v);
          }
          $value = implode(', ', $value);
          break;
        default:
          throw new Exception("Unknown conversion %L{$next}.");
      }
      break;
  }

  if (!$done) {
    _qsprintf_check_type($value, $type, $pattern);
    switch ($type) {
      case 's': // String
        if ($nullable && $value === null) {
          $value = 'NULL';
        } else {
          $value = "'".$conn->escapeString($value)."'";
        }
        $type = 's';
        break;

      case 'Q': // Query Fragment
        $type = 's';
        break;

      case '~': // Like Substring
      case '>': // Like Prefix
      case '<': // Like Suffix
        $value = $conn->escapeStringForLikeClause($value);
        switch ($type) {
          case '~': $value = "'%".$value."%'"; break;
          case '>': $value = "'" .$value."%'"; break;
          case '<': $value = "'%".$value. "'"; break;
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
        $value = $conn->escapeColumnName($value);
        $type = 's';
        break;

      case 'K': // Komment
        $value = $conn->escapeMultilineComment($value);
        $type = 's';
        break;

      default:
        throw new Exception("Unknown conversion '%{$type}'.");

    }
  }

  if ($prefix) {
    $value = $prefix.$value;
  }
  $pattern[$pos] = $type;
}


/**
 * @group storage
 */
function _qsprintf_check_type($value, $type, $query) {
  switch ($type) {
    case 'Ld': case 'Ls': case 'LC': case 'LA': case 'LO':
      if (!is_array($value)) {
        throw new AphrontQueryParameterException(
          $query,
          "Expected array argument for %{$type} conversion.");
      }
      if (empty($value)) {
        throw new AphrontQueryParameterException(
          $query,
          "Array for %{$type} conversion is empty.");
      }

      foreach ($value as $scalar) {
        _qsprintf_check_scalar_type($scalar, $type, $query);
      }
      break;
    default:
      _qsprintf_check_scalar_type($value, $type, $query);
      break;
  }
}


/**
 * @group storage
 */
function _qsprintf_check_scalar_type($value, $type, $query) {
  switch ($type) {
    case 'Q': case 'LC': case 'T': case 'C':
      if (!is_string($value)) {
        throw new AphrontQueryParameterException(
          $query,
          "Expected a string for %{$type} conversion.");
      }
      break;

    case 'Ld': case 'd': case 'f':
      if (!is_null($value) && !is_numeric($value)) {
        throw new AphrontQueryParameterException(
          $query,
          "Expected a numeric scalar or null for %{$type} conversion.");
      }
      break;

    case 'Ls': case 's':
    case '~': case '>': case '<': case 'K':
      if (!is_null($value) && !is_scalar($value)) {
        throw new AphrontQueryParameterException(
          $query,
          "Expected a scalar or null for %{$type} conversion.");
      }
      break;

    case 'LA': case 'LO':
      if (!is_null($value) && !is_scalar($value) &&
          !(is_array($value) && !empty($value))) {
        throw new AphrontQueryParameterException(
          $query,
          "Expected a scalar or null or non-empty array for ".
          "%{$type} conversion.");
      }
      break;
    default:
      throw new Exception("Unknown conversion '{$type}'.");
  }
}
