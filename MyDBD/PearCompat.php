<?php

/**
 * @package MyDBD
 */

/**
 * This class add to MyDBD some methods to maintain compatibility with PEAR::Db. This can
 * helps people wanting to migrate from this library without having to rewrite a lot of code.
 *
 * @package MyDBD
 * @author Olivier Poitrey (rs@dailymotion.com)
 */
abstract class MyDBD_PearCompat
{
    static protected
        $defaultFetchMode = array();

    static public function init()
    {
        if (!defined('DB_FETCHMODE_DEFAULT'))
        {
            define('DB_FETCHMODE_DEFAULT', 1);
            define('DB_FETCHMODE_ORDERED', 1);
            define('DB_FETCHMODE_ASSOC',   2);
            define('DB_FETCHMODE_OBJECT',  3);
        }
    }

    /**#@+ @deprecated */

    static public function setFetchMode(MyDBD $dbh, $fetchMode)
    {
        self::$defaultFetchMode[spl_object_hash($dbh)] = $fetchMode;
    }

    static protected function getFetchMode(MyDBD $dbh, $fetchMode)
    {
        if ($fetchMode === DB_FETCHMODE_DEFAULT)
        {
            if (isset(self::$defaultFetchMode[spl_object_hash($dbh)]))
            {
                return self::$defaultFetchMode[spl_object_hash($dbh)];
            }
            else
            {
                return DB_FETCHMODE_ORDERED;
            }
        }
        else
        {
            return $fetchMode;
        }
    }

    static public function isError(MyDBD $dbh)
    {
        return false;
    }

    static public function autoCommit(MyDBD $dbh, $state)
    {
        if (!$state)
        {
            $dbh->begin();
        }
        else
        {
            throw new Exception('The autoCommit(false) method is not implemented.');
        }
    }

    static public function affectedRows(MyDBD $dbh)
    {
        return $dbh->getAffectedRows();
    }

    static public function execute(MyDBD $dbh, MyDBD_PreparedStatement $statement, $params = null)
    {
        return call_user_func_array(array($statement, 'execute'), !is_array($params) ? array($params) : $params);
    }

    static public function fetchRow(MyDBD_ResultSet $res, $fetchMode = null)
    {
        return $res->next($fetchMode);
    }

    static public function fetchInto(MyDBD_ResultSet $res, &$row, $fetchMode = null)
    {
        $row = $res->next($fetchMode);
        return isset($row);
    }

    static public function getCol(MyDBD $dbh, $query, $col = 0, $params = array())
    {
        return $dbh->query($query, $params)->setFetchMode(MyDBD::FETCH_COLUMN, $col)->fetchAll();
    }

    static public function getOne(MyDBD $dbh, $query, $params = array())
    {
        return $dbh->query($query, $params)->fetchColumn(0);
    }

    static public function getRow(MyDBD $dbh, $query, $params = array(), $fetchMode = DB_FETCHMODE_DEFAULT)
    {
        return $dbh->query($query, $params)->next(self::getFetchMode($dbh, $fetchMode));
    }

    /**
     * Runs the query provided and puts the entire result set into a nested array.
     *
     * @see http://pear.php.net/manual/en/package.database.db.db-common.getall.php
     */
    static public function getAll(MyDBD $dbh, $query, $params = array(), $fetchMode = DB_FETCHMODE_DEFAULT)
    {
        return $dbh->query($query, $params)->setFetchMode(self::getFetchMode($dbh, $fetchMode))->fetchAll();
    }

    /**
     * Runs a query and returns the data as an array.
     *
     * If the result set contains more than two columns, the value will be an array of the values
     * from column 2 to n. If the result set contains only two columns, the returned value will
     * be a scalar with the value of the second column (unless forced to an array with the
     * $force_array parameter).
     *
     * @see http://pear.php.net/manual/en/package.database.db.db-common.getassoc.php
     */
    static public function getAssoc(MyDBD $dbh, $query, $forceArray = false, $params = array(), $fetchMode = DB_FETCHMODE_DEFAULT, $group = false)
    {
        $res = $dbh->query($query, $params);

        $cols = $res->getFieldCount();

        if ($cols < 2)
        {
            throw new SQLTruncatedException();
        }

        $results = array();

        if ($cols > 2 || (isset($force_array) && $force_array))
        {
            switch(self::getFetchMode($dbh, $fetchMode))
            {
                case DB_FETCHMODE_ASSOC:
                    while (is_array($row = $res->fetchAssoc()))
                    {
                        reset($row);
                        $key = current($row);
                        unset($row[key($row)]);
                        if($group)
                        {
                            $results[$key][] = $row;
                        }
                        else
                        {
                            $results[$key] = $row;
                        }
                    }
                    break;

                case DB_FETCHMODE_OBJECT:
                    while ($row = $res->fetchObject())
                    {
                        $arr = get_object_vars($row);
                        $key = current($arr);
                        if ($group)
                        {
                            $results[$key][] = $row;
                        }
                        else
                        {
                            $results[$key] = $row;
                        }
                    }
                    break;

                default:
                    while (is_array($row = $res->fetchArray()))
                    {
                        // we shift away the first element to get
                        // indices running from 0 again
                        $key = array_shift($row);
                        if ($group)
                        {
                            $results[$key][] = $row;
                        }
                        else
                        {
                            $results[$key] = $row;
                        }
                    }
                    break;
            }
        }
        else
        {
            // return scalar values
            while (is_array($row = $res->fetchRow(DB_FETCHMODE_ORDERED)))
            {
                if ($group)
                {
                    $results[$row[0]][] = $row[1];
                }
                else
                {
                    $results[$row[0]] = $row[1];
                }
            }
        }

        return $results;
    }

    /**#@-*/
}
