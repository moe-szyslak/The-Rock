<?php
  class Moedoo {
    /**
     * returns a FK reference
     *
     * @param string $table
     * @param array $rows
     * @param array $depth
     * @return array
     */
    public static function referenceFk($table, $rows, &$depth = 1) {
      if ($depth > 0 || $depth === -1) {
        if ($depth > 0) {
          $depth--;
        }

        if (array_key_exists('fk', Config::get('TABLES')[$table]) === true) {
          foreach (Config::get('TABLES')[$table]['fk'] as $column => $referenceRule) {
            // [col_name] --- multiple columns reference
            if (preg_match('/^\[.+\]$/', $column) === 1) {
              $column = trim($column, '[]'); // stripping the flags

              $INCLUDES = [];
              foreach ($rows as $index => &$row) {
                foreach ($row[$column] as $index => $value) {
                  $INCLUDES[$value] = $value;
                }
              }

              $columns = Moedoo::buildReturn($referenceRule['table']);
              $INCLUDES = str_replace(', ,', ',', implode(', ', $INCLUDES));
              $query = "SELECT {$columns} FROM ". Config::get('TABLE_PREFIX') ."{$referenceRule['table']} WHERE {$referenceRule['references']} = ANY(ARRAY[$INCLUDES])";

              try {
                $includeRows = Moedoo::executeQuery($referenceRule['table'], $query, []);
                $includeRows = Moedoo::cast($referenceRule['table'], $includeRows);

                $illBeBack = $depth;
                while ($depth > 0) { $includeRows = Moedoo::referenceFk($referenceRule['table'], $includeRows, $depth); }
                $depth = $illBeBack;

                // building map table...
                // include row id => include row
                $includeRowsMap = [];
                foreach ($includeRows as $index => $includeRow) {
                  $includeRowsMap[$includeRow[$referenceRule['references']]] = $includeRow;
                }

                // setting fk using the map...
                foreach ($rows as $index => &$row) {
                  foreach ($row[$column] as $index => $value) {
                    if (isset($includeRowsMap[$row[$column][$index]])) {
                      $row[$column][$index] = $includeRowsMap[$row[$column][$index]];
                    } else {
                      $row[$column][$index] = null; // reference no longer exits
                    }
                  }
                }
              } catch (Exception $e) {
                throw new Exception($e->getMessage(), 1);
              }
            }

            /**
             * {col_name} --- reverse reference™️
             */
            else if (preg_match('/^\{.+\}$/', $column) === 1) {
              $column = trim($column, '{}');

              $INCLUDES = [];
              $REFERENCE_KEY = Config::get('REFERENCE_KEY');
              foreach ($rows as $index => $row) {
                array_push($INCLUDES, $row[$referenceRule['referenced_by']]);
              }

              $columns = Moedoo::buildReturn($referenceRule['table']);
              $INCLUDES = str_replace(', ,', ',', implode(', ', $INCLUDES));

              // enforcing fk to be limited to int type
              if (in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[int]'])) {
                $query = "SELECT {$columns} FROM ". Config::get('TABLE_PREFIX') ."{$referenceRule['table']} WHERE {$referenceRule['referencing_column']} && ARRAY[$INCLUDES]";
              }

              else if (in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['int'])) {
                $query = "SELECT {$columns} FROM ". Config::get('TABLE_PREFIX') ."{$referenceRule['table']} WHERE {$referenceRule['referencing_column']} = ANY(ARRAY[$INCLUDES])";
              }

              try {
                $includeRows = Moedoo::executeQuery($referenceRule['table'], $query, []);
                $includeRows = Moedoo::cast($referenceRule['table'], $includeRows);

                $illBeBack = $depth;
                while ($depth > 0) { $includeRows = Moedoo::referenceFk($referenceRule['table'], $includeRows, $depth); }
                $depth = $illBeBack;

                // this block will be looking for reference in [fk, fk]
                if (in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[int]'])) {
                  foreach ($rows as $index => &$row) {
                    $reverseInclude = [];

                    foreach ($includeRows as $includeIndex => $includeRow) {
                      if (in_array($row[$referenceRule['referenced_by']], $includeRow[$referenceRule['referencing_column']])) {
                        array_push($reverseInclude, $includeRow);
                      }
                    }

                    $row[$REFERENCE_KEY][$column] = $reverseInclude;
                  }
                }

                else if (in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['int'])) {
                  foreach ($rows as $index => &$row) {
                    $reverseInclude = [];

                    foreach ($includeRows as $includeIndex => $includeRow) {
                      if ($includeRow[$referenceRule['referencing_column']] === $row[$referenceRule['referenced_by']]) {
                        array_push($reverseInclude, $includeRow);
                      }
                    }

                    $row[$REFERENCE_KEY][$column] = $reverseInclude;
                  }
                }
              } catch (Exception $e) {
                throw new Exception($e->getMessage(), 1);
              }
            }

            /**
             * col_name --- single foreign key reference
             */
            else {
              // building hash map of unique fk id (better performance than `array_unique`)
              // associative array [id => id];
              $INCLUDES = [];
              foreach ($rows as $index => $row) {
                $INCLUDES[$row[$column]] = $row[$column];
              }

              $columns = Moedoo::buildReturn($referenceRule['table']);
              $INCLUDES = str_replace(', ,', ',', implode(', ', $INCLUDES));
              $query = "SELECT {$columns} FROM ". Config::get('TABLE_PREFIX') ."{$referenceRule['table']} WHERE {$referenceRule['references']} = ANY(ARRAY[$INCLUDES])";

              try {
                $includeRows = Moedoo::executeQuery($referenceRule['table'], $query, []);
                $includeRows = Moedoo::cast($referenceRule['table'], $includeRows);

                $illBeBack = $depth;
                while ($depth > 0) { $includeRows = Moedoo::referenceFk($referenceRule['table'], $includeRows, $depth); }
                $depth = $illBeBack;

                // building map table...
                // include row id => include row
                $includeRowsMap = [];
                foreach ($includeRows as $index => $includeRow) {
                  $includeRowsMap[$includeRow[$referenceRule['references']]] = $includeRow;
                }

                // setting fk using the map...
                foreach ($rows as $index => &$row) {
                  if (isset($includeRowsMap[$row[$column]])) {
                    $row[$column] = $includeRowsMap[$row[$column]];
                  } else {
                    $row[$column] = null; // reference no longer exits
                  }
                }
              } catch (Exception $e) {
                throw new Exception($e->getMessage(), 1);
              }
            }
          }
        }
      }

      return $rows;
    }


    /**
     * builds a returning table syntax
     *
     * this is to be used for casting special columns like geometry
     *
     * @param string $table
     * @return array
     */
    public static function buildReturn($table) {
      $build = '';

      foreach (Config::get('TABLES')[$table]['returning'] as $index => $column) {
        if (array_key_exists('geometry', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['geometry']) === true) {
          $build .= "ST_AsGeoJSON({$column}) as {$column}, ";
        } else {
          $build .= "{$column}, ";
        }
      }

      return substr($build, 0, -2);
    }


    /**
     * given an array it'll cast accordingly so that the db operation can
     * be done without a glitch --- fingers crossed
     *
     * @param string $table - table on which to apply the casting for
     * @param array $data - data to be prepared for db operation
     * @return array - database operation ready data
     */
    public static function castForPg($table, $data) {
      foreach ($data as $column => &$value) {
        if (array_key_exists('JSON', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['JSON']) === true) {
          $value = json_encode($value);
        }

        if (array_key_exists('bool', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['bool']) === true) {
          $value = $value === true ? 'TRUE' : 'FALSE';
        }

        if (array_key_exists('geometry', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['geometry']) === true) {
          $value = json_encode($value);
        }

        if ( (array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) ||
            (array_key_exists('[float]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[float]']) === true) ||
            (array_key_exists('[double]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[double]']) === true) ) {
          $value = '{'. implode(',', $value) .'}';
        }
      }

      return $data;
    }


    /**
     * given an array of rows straight out of pg it'll cast the appropriate
     * type according to `config`
     *
     * @param string $table - table on which to apply the casting
     * @param array $rows
     * @return array
     */
    public static function cast($table, $rows) {
      // we have rows, setting rows...
      // this right here saves gives 200% performance boost on large datasets query
      $CAST_FLAG = [
        'JSON' => [],
        'geometry' => [],
        'int' => [],
        'float' => [],
        'double' => [],
        'bool' => [],
        '[int]' => []
      ];

      if (count($rows) > 0) {
        foreach (Config::get('TABLES')[$table]['returning'] as $index => $column) {
          $CAST_FLAG['JSON'][$column] = array_key_exists('JSON', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['JSON']) === true;
          $CAST_FLAG['geometry'][$column] = array_key_exists('geometry', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['geometry']) === true;
          $CAST_FLAG['int'][$column] = array_key_exists('int', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['int']) === true;
          $CAST_FLAG['float'][$column] = array_key_exists('float', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['float']) === true;
          $CAST_FLAG['double'][$column] = array_key_exists('double', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['double']) === true;
          $CAST_FLAG['bool'][$column] = array_key_exists('bool', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['bool']) === true;
          $CAST_FLAG['[int]'][$column] = array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true;
        }
      }

      foreach ($rows as $index => &$row) {
        foreach ($row as $column => &$value) {
          if ($CAST_FLAG['JSON'][$column] === true) {
            $value = json_decode($value);
          }

          else if ($CAST_FLAG['geometry'][$column] === true) {
            $value = json_decode($value);
          }

          else if ($CAST_FLAG['int'][$column] === true) {
            $value = is_numeric($value) === true ? (int)$value : null;
          }

          else if ($CAST_FLAG['float'][$column] === true) {
            $value = is_numeric($value) === true ? (float)$value : null;
          }

          else if ($CAST_FLAG['double'][$column] === true) {
            $value = is_numeric($value) === true ? (double)$value : null;
          }

          else if ($CAST_FLAG['bool'][$column] === true) {
            $value = $value === 't' ? true : false;
          }

          // for now (and probably forever) we can only work with 1D arrays
          // since we'll have PG version 8 we can't use JSON :(
          else if ($CAST_FLAG['[int]'][$column] === true) {
            $value = trim($value, '{}');
            $value = $value === '' ? [] : explode(',', $value);
            foreach ($value as $index => &$v) {
              $v = is_numeric($v) === true ? (int)$v : null;
            }
          }
        }
      }

      return $rows;
    }


    /**
     * query executor
     *
     * EXCEPTION CODES
     * 1: unable to update for unknown reason[s]
     * 2: duplicate constraint
     * 3: foreign key constraint
     *
     * @param  String $table
     * @param  String $query
     * @param  Array $params
     * @return Array
     */
    public static function executeQuery($table, $query, $params = []) {
      $dbConnection = Moedoo::db(Config::get('DB_HOST'), Config::get('DB_PORT'), Config::get('DB_USER'), Config::get('DB_PASSWORD'), Config::get('DB_NAME'));

      if (pg_send_query_params($dbConnection, $query, $params)) {
        $resource = pg_get_result($dbConnection);
        $state = pg_result_error_field($resource, PGSQL_DIAG_SQLSTATE);
        if ($state == 0) {
          if ($resource === false || pg_fetch_all($resource) === false) {
            return [];
          } else {
            return pg_fetch_all($resource);
          }
        } else {
          switch($state) {
            // duplicate
            case '23505':
              throw new Exception('request violets duplicate constraint', 2);
            break;

            // foreign key
            case '23503':
              throw new Exception('request violets foreign key constraint', 3);
            break;

            default:
              $querySelect = preg_match('/^SELECT/', $query);
              $queryInsert = preg_match('/^INSERT/', $query);
              $queryUpdate = preg_match('/^UPDATE/', $query);
              $queryDelete = preg_match('/^DELETE/', $query);

              // we won't be giving detailed error in order "protect" the system
              if ($querySelect === 1) {
                throw new Exception("unable to select from table `{$table}`", 1);
              } else if ($queryInsert === 1) {
                throw new Exception("unable to save `{$table}`", 1);
              } else if ($queryUpdate === 1) {
                throw new Exception("unable to update `{$table}`", 1);
              } else if ($queryDelete === 1) {
                throw new Exception("unable to delete record from table `{$table}`", 1);
              } else {
                throw new Exception('error processing query', 1);
              }
            break;
          }
        }
      } else {
        throw new Exception("unable to process query on table `{$table}`", 1);
      }
    }


    /**
     * instantiates db connection
     *
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $password
     * @param string $dbname
     * @param string $host
     * @return connection resource
     */
    public static function db($host, $port, $user, $password, $dbname) {
      $dbConnection = pg_pconnect("host={$host} port={$port} user={$user} password={$password} dbname={$dbname}");
      if ($dbConnection === false) {
        Rock::halt(500, 'unable to connect to database');
      }

      return $dbConnection;
    }


    /**
     * returns matching rows against query
     *
     * @param string $table
     * @param string $query
     * @param string $limit
     * @param integer $depth
     */
    public static function search($table, $q, $limit = 'ALL', $depth = 1) {
      $columns = Moedoo::buildReturn($table);
      $q = preg_replace('/ +/', '|', trim($q));
      $q = preg_replace('/ /', '|', $q);
      $params = [$q];
      $query = "SELECT {$columns} FROM ". Config::get('TABLE_PREFIX') ."{$table} WHERE ";
      $where = '';
      $order_by = 'ORDER BY';

      // model doesn't have any full-text fields
      if (count(Config::get('TABLES')[$table]['search']) === 0) {
        Rock::halt(400, "table `{$table}` has no searchable fields");
      }

      else {
        // building vector...
        foreach (Config::get('TABLES')[$table]['search'] as $key => $value) {
          $where .= "to_tsvector({$value}) @@ to_tsquery($1) OR ";
        }

        $where = substr($where, 0, -4);

        // building rank...
        foreach (Config::get('TABLES')[$table]['search'] as $key => $value) {
          $order_by .= " ts_rank(to_tsvector($value), to_tsquery($1)) DESC, ";
        }

        $order_by = substr($order_by, 0, -2);
        $limit = "LIMIT {$limit}";
        try {
          $rows = Moedoo::executeQuery($table, "{$query} {$where} {$order_by} {$limit};", $params);

          if (count($rows) === 0) {
            return [];
          }

          else {
            $rows = Moedoo::cast($table, $rows);
            $rows = Moedoo::referenceFk($table, $rows, $depth);
            return $rows;
          }
        } catch (Exception $e) {
          throw new Exception($e->getMessage(), 1);
        }
      }
    }


    /**
     * returns row count on a table
     *
     * @param string $table
     * @return integer
     */
    public static function count($table) {
      $query = 'SELECT count('. Config::get('TABLES')[$table]['pk'] .') as count FROM '. Config::get('TABLE_PREFIX') ."{$table};";
      $params = [];
      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        if (count($rows) === 0) {
          $count = 0;
        }

        else {
          $count = (int)$rows[0]['count'];
        }

        return $count;
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
      }
    }


    /**
     * executes SELECT command on a given table
     *
     * @param string $table - table to operate select command on
     * @param array $and - concatenated with `AND`
     * @param array $or - concatenated with `OR`
     * @param array $depth - -1 implies FULL depth [not recommended]
     * @param integer $limit - limit on row limit
     * @param integer $offset - offset on query
     * @return affected rows or null if an error occurred
     */
    public static function select($table, $and = null, $or = null, &$depth = 1, $limit = 'ALL', $offset = 0) {
      $columns = Moedoo::buildReturn($table);
      $query = "SELECT {$columns} FROM ". Config::get('TABLE_PREFIX') ."{$table}";
      $params = [];

      if ($and === null && $or === null) {
        $query .= ' ORDER BY '. Config::get('TABLES')[$table]['pk'] .' DESC ';
      }

      else {
        $query .= ' WHERE';

        if ($and !== null) {
          $query .= ' (';

          foreach ($and as $column => $value) {
            array_push($params, $value);

            if ((array_key_exists('[bool]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[bool]']) === true) ||
               (array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) ||
               (array_key_exists('[float]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[float]']) === true) ||
               (array_key_exists('[double]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[double]']) === true) ||
               (array_key_exists('[JSON]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[JSON]']) === true) ||
               (array_key_exists('[geometry]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[geometry]']) === true)) {
              $query .= '$'. count($params) ."=ANY({$column}) AND ";
            } else {
              $query .= "{$column}=$". count($params) .' AND ';
            }
          }

          $query = substr($query, 0, -5);
          $query .= ')';

          if ($or !== null) {
            $query .= ' AND';
          }
        }

        if ($or !== null) {
          $query .= ' (';

          foreach ($or as $column => $value) {
            array_push($params, $value);

            if ((array_key_exists('[bool]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[bool]']) === true) ||
               (array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) ||
               (array_key_exists('[float]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[float]']) === true) ||
               (array_key_exists('[double]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[double]']) === true) ||
               (array_key_exists('[JSON]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[JSON]']) === true) ||
               (array_key_exists('[geometry]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[geometry]']) === true)) {
              $query .= '$'. count($params) ."=ANY({$column}) OR ";
            } else {
              $query .= "{$column}=$".count($params).' OR ';
            }
          }

          $query = substr($query, 0, -4);
          $query .= ')';
        }

        $query .= ' ORDER BY '. Config::get('TABLES')[$table]['pk'] .' DESC ';
      }

      $query .= "LIMIT {$limit} OFFSET {$offset};";
      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        if (count($rows) === 0) {
          return [];
        }

        else {
          $rows = Moedoo::cast($table, $rows);
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows;
        }
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
      }
    }


    /**
     * executes INSERT command on a given table - one at a time
     *
     * @param string $table - table name without prefix
     * @param array $data - data to be inserted
     * @return array - the newly inserted row
     */
    public static function insert($table, $data, $depth = 1) {
      $data = Moedoo::castForPg($table, $data);
      $count = 1;
      $columns = [];
      $holders = []; // ${$index}
      $params = [];

      foreach ($data as $column => $value) {
        array_push($columns, $column);

        if (array_key_exists('geometry', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['geometry']) === true) {
          array_push($holders, "ST_GeomFromGeoJSON(\${$count})");
        }

        else {
          array_push($holders, "\${$count}");
        }

        array_push($params, $value);
        $count++;
      }

      $columns = implode(', ', $columns);
      $holders = implode(', ', $holders);
      $returning = Moedoo::buildReturn($table);

      $query = 'INSERT INTO '. Config::get('TABLE_PREFIX') ."{$table} ({$columns}) VALUES ({$holders}) RETURNING {$returning};";
      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        // currently we're only supporting single row insert
        if (count($rows) === 1) {
          $rows = Moedoo::cast($table, $rows);
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows[0];
        } else {
          throw new Exception('error processing query', 1);
        }
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
      }
    }


    /**
     * executes UPDATE on a given tale entry
     *
     * 1 - patch request on non-existent data
     *
     * @param string $table
     * @param array $data - data which to replace on
     * @return array | null
     */
    public static function update($table, $data, $id, $depth = 1) {
      $data = Moedoo::castForPg($table, $data);
      $count = 1;
      $set = [];
      $params = [];
      $columns = Moedoo::buildReturn($table);

      foreach ($data as $column => $value) {
        if (array_key_exists('geometry', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['geometry']) === true) {
          array_push($set, $column."=ST_GeomFromGeoJSON(\${$count})");
        }

        else {
          array_push($set, $column."=\${$count}");
        }

        array_push($params, $value);
        $count++;
      }

      $set = implode(', ', $set);
      array_push($params, $id);

      $query = 'UPDATE '. Config::get('TABLE_PREFIX') ."{$table} SET {$set} WHERE ". Config::get('TABLES')[$table]['pk'] ."=\${$count} RETURNING {$columns};";
      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        // currently we're only supporting single row update
        if (count($rows) === 1) {
          $rows = Moedoo::cast($table, $rows);
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows[0];
        }

        // no row was affected, returns the data back...
        else {
          throw new Exception("`{$table}` entry with id `{$id}` does not exist", 1);
        }
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
      }
    }


    /**
     * executes DELTE command on a given entry
     * i didn't want to do this, but...
     * there comes a time were you want to just delete
     *
     * EXCEPTION CODES
     * 1: unable to update for unknown reason[s]
     *
     * @param string $table
     * @param integer $id - id on which to delete on
     * @return array - deleted row
     */
    public static function delete($table, $id) {
      $params = [$id];
      $columns = Moedoo::buildReturn($table);

      $query = 'DELETE FROM '. Config::get('TABLE_PREFIX') ."{$table} WHERE ". Config::get('TABLES')[$table]['pk'] ."=$1 RETURNING {$columns};";
      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        // currently we're only supporting single row deletion
        if (count($rows) === 1) {
          return $rows[0];
        } elseif (count($rows) === 0) {
          throw new Exception("`{$table}` with resource id `{$id}` does not exist", 1);
        }
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
      }
    }
  }
