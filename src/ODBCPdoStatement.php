<?php

/**
 * Created by PhpStorm.
 * User: Andrea
 * Date: 23/02/2018
 * Time: 17:51
 */

namespace Abram\Odbc;

use PDOStatement;

class ODBCPdoStatement extends PDOStatement
{
    protected $query;
    protected $params = [];
    protected $statement;

    private $conn = null;

    public function __construct($conn, $query)
    {
        $this->query = preg_replace('/(?<=\s|^):[^\s:]++/um', '?', $query);

        $this->params = $this->getParamsFromQuery($query);

        $this->conn = $conn;
    }

    protected function getParamsFromQuery($qry)
    {
        $params = [];
        $qryArray = explode(" ", $qry);
        $i = 0;

        while (isset($qryArray[$i])) {
            if (preg_match("/^:/", $qryArray[$i]))
                $params[$qryArray[$i]] = null;
            $i++;
        }

        return $params;
    }

    public function rowCount()
    {
        return odbc_num_rows($this->statement);
    }

    public function bindValue($param, $val, $ignore = null)
    {
        $this->params[$param] = $val;
    }

    public function execute($ignore = null)
    {
        $explodedQuery = explode('?', $this->query);
        $parameterizedQuery = '';

        $i = 1;
        while (isset($this->params[$i])) {
            if (gettype($this->params[$i]) == 'integer') {
                $parameterizedQuery = $parameterizedQuery . $explodedQuery[$i - 1] . $this->params[$i];
            } else {
                $val = $this->mysql_escape_mimic($this->params[$i]);
                $parameterizedQuery = $parameterizedQuery . $explodedQuery[$i - 1] . "'" . $val . "'";
            }
            $i++;
        }

        $parameterizedQuery = $parameterizedQuery . $explodedQuery[count($explodedQuery) - 1];

        $this->params = [];

        $this->statement = odbc_prepare($this->conn, $parameterizedQuery);
        odbc_execute($this->statement, $this->params);
    }
    public function setFetchMode(int $mode, mixed ...$args)
    {
    }

    public function fetchAll(int $mode = \PDO::FETCH_BOTH, mixed ...$args)
    {
        $records = [];
        while ($record = $this->fetch()) {
            $records[] = $record;
        }
        return $records;
    }

    public function fetch($option = null, $ignore = null, $ignore2 = null)
    {
        return odbc_fetch_array($this->statement);
    }

    private static function mysql_escape_mimic($inp)
    {
        if (is_array($inp))
            return array_map(__METHOD__, $inp);

        if (!empty($inp) && is_string($inp)) {
            return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
        }

        return $inp;
    }
}
