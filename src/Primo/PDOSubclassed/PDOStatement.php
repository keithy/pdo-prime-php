<?php

namespace Primo\PDOSubclassed;

class PDOStatement extends \PDOStatement
{

    protected $logs;
    protected $bindings = [];

    protected function __construct(\Primo\PDOSubclassed\PDO $pdo)
    {
        $this->logs = $pdo->getLogs();
    }

    function execute($params = null)
    {
        $start = microtime(true);
        try {
            $result = $this->parentExecute($params);
        } finally {

            if (isset($this->logs)) {
                $this->logThis($start, $this->queryString(), $params ?? $this->bindings, $result);
            }
        }
        return $result;
    }

    // manually reconstruct query for logging
    function logThis($start, $sql, $bindings, $result)
    {
        foreach ($bindings as $param => $value) {
            $value = (is_numeric($value) or is_null($value)) ? $value : "`{$this->quote($value)}`";
            $value = is_null($value) ? "null" : $value;
            if ($param[0] !== ':') $param = ":{$param}"; // insert missing colon

            $sql = preg_replace("/\?|$param(?![a-zA-Z_])/", $value, $sql, 1);
        }
        $ms = 1000 * (microtime(true) - $start);
        
        $this->logs->logThis($sql, $ms, $result);
    }

    function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $maxlen = NULL, $driverdata = NULL)
    {
        $this->bindings[$parameter] = $variable;
        return parent::bindParam($parameter, $variable, $data_type);
    }

    function bindValue($parameter, $variable, $data_type = \PDO::PARAM_STR)
    {
        $this->bindings[$parameter] = $variable;
        return parent::bindValue($parameter, $variable, $data_type);
    }

    function queryString()
    {
        return $this->queryString;
    }

    function parentExecute($params)
    {
        return parent::execute($params);
    }

    function mode(...$args)
    {
        if (!$this->setFetchMode(...$args)) {
            throw new \PDO\Exception('setFetchMode() failed');
        }
        return $this;
    }

    function as($classRef, ...$args)
    {
        if (!$this->setFetchMode(\PDO::FETCH_CLASS, $classRef, ...$args)) {
            throw new \PDO\Exception('setFetchMode($classRef) failed');
        }
        return $this;
    }

    function quote($value)
    {
        $search = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
        $replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");

        return str_replace($search, $replace, $value);
    }

    function fetchAllAsObjects($class_name = null, $ctor_args = null)
    {
        return $this->fetchAll(\PDO::FETCH_CLASS, $class_name, $ctor_args);
    }

    function fetchAllAsColumn($n = 0)
    {
        return $this->fetchAll(\PDO::FETCH_COLUMN, $n);
    }

}
