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
    static public function init()
    {
        if (!defined('DB_FETCHMODE_DEFAULT'))
        {
            define('DB_FETCHMODE_DEFAULT', 0);
            define('DB_FETCHMODE_ORDERED', 1);
            define('DB_FETCHMODE_ASSOC',   2);
            define('DB_FETCHMODE_OBJECT',  3);
        }
    }

    static public function affectedRows(MyDBD $dbh)
    {
        return $dbh->getAffectedRows();
    }

    static public function execute(MyDBD $dbh, MyDBD_PreparedStatement $statement, $params = null)
    {
        if (isset($params) && !is_array($params))
        {
            $params = array($params);
        }

        return call_user_func_array(array($statement, 'execute'), $params);
    }

    static public function fetchRow(MyDBD_ResultSet $res, $mode = DB_FETCHMODE_DEFAULT)
    {
        if ($mode == DB_FETCHMODE_DEFAULT) $mode = DB_FETCHMODE_ORDERED;
        $row = $res->next($mode);
        return $row;
    }

    static public function fetchInto(MyDBD_ResultSet $res, &$row, $mode = DB_FETCHMODE_DEFAULT)
    {
        $row = MyDBD_PEARCompat::fetchRow($res, $mode);
    }

    static public function getCol(MyDBD $dbh, $query, $col = 0, $params = array())
    {
        $res = $dbh->query($query, $params);

        $fetchmode = is_int($col) ? DB_FETCHMODE_ORDERED : DB_FETCHMODE_ASSOC;

        if (!is_array($row = MyDBD_PEARCompat::fetchRow($res, $fetchmode)))
        {
            $ret = array();
        }
        else
        {
            if (!array_key_exists($col, $row))
            {
                throw new SQLNoSuchFieldException();
            }
            else
            {
                $ret = array($row[$col]);
                while (is_array($row = MyDBD_PEARCompat::fetchRow($res, $fetchmode)))
                {
                    $ret[] = $row[$col];
                }
            }
        }

        return $ret;
    }

    static public function getOne(MyDBD $dbh, $query, $params = array())
    {
        $res = $dbh->query($query, $params);
        MyDBD_PEARCompat::fetchInto($res, $row, DB_FETCHMODE_ORDERED);
        return $row[0];
    }

    static public function getRow(MyDBD $dbh, $query, $params = array(), $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        $res = $dbh->query($query, $params);
        MyDBD_PEARCompat::fetchInto($res, $row, $fetchmode);
        return $row;
    }

    /**
     * Runs the query provided and puts the entire result set into a nested array.
     *
     * @see http://pear.php.net/manual/en/package.database.db.db-common.getall.php
     */
    static public function getAll(MyDBD $dbh, $query, $params = array(), $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if ($fetchmode == DB_FETCHMODE_DEFAULT) $fetchmode = DB_FETCHMODE_ORDERED;
        $res = $dbh->query($query, $params);
        $res->setFetchMode($fetchmode);
        $result = array();
        foreach ($res as $row)
        {
            $result[] = $row;
        }
        return $result;
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
            switch($fetchmode)
            {
                case DB_FETCHMODE_ASSOC:
                    while (is_array($row = $res->fetchRowAsAssoc()))
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
                    while ($row = MyDBD_PEARCompat::fetchRow($res, DB_FETCHMODE_OBJECT))
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
                    while (is_array($row = $res->fetchRow(DB_FETCHMODE_ORDERED)))
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
}