<?php

/**
 * @package MyDBD
 */

/**
 * @package MyDBD
 * @author Olivier Poitrey (rs@dailymotion.com)
 */
class MyDBD_ResultSet implements SeekableIterator, Countable
{
    /**#@+ @ignore */
    protected
        $result     = null,
        $options    = null,
        $cursor     = 0,
        $fetchMode  = MyDBD::FETCH_ORDERED,
        $fetchClass = 'stdClass',
        $fetchCol   = 0;
    /**#@-*/

    /**
     * This object is meant to be created by MyDBD.
     *
     * @param mysqli_result $result
     */
    public function __construct(mysqli_result $result, array $options)
    {
        $this->result  = $result;
        $this->options = $options;
    }

    /**
     * Sets the default fetch mode used by curret() and next().
     *
     * @param integer $mode Ether MyDBD::FETCH_ORDERED, MyDBD::FETCH_ASSOC,
     *                      MyDBD::FETCH_OBJECT or MyDBD::FETCH_COLUMN.
     *
     * - FETCH_ORDERED: Result is stored in an array of string with numerical keys in the order
     *                  of the fields of the query.
     * - FETCH_ASSOC:   Result is stored in an associative array with keys named like fields of
     *                  the query
     * - FETCH_OBJECT:  Result is stored in properties of an object.
     * - FETCH_COLUMN:  Result is stored in a string containing only the asked column.
     *
     * @param string|integer $arg Optional argument for some fetch modes:
     *
     * - If mode is MyDBD::FETCH_OBJECT, this parameter will change the class used to create
     *   the object. If not provided, stdClass is used by default.
     * - If mode is MyDBD::FETCH_COLUMN, this parameter defines which column to fetch.
     *   The column can be expressed either as a numeric index or as string field name. If not
     *   provided, 0 is used.
     *
     * @return $this
     */
    public function setFetchMode($mode, $arg = null)
    {
        if ($mode !== MyDBD::FETCH_ORDERED && $mode !== MyDBD::FETCH_ASSOC && $mode !== MyDBD::FETCH_OBJECT && $mode !== MyDBD::FETCH_COLUMN)
        {
            throw InvalidArgumentException('Invalid fetch mode: ' . $mode);
        }

        $this->fetchMode = $mode;

        switch ($mode)
        {
            case MyDBD::FETCH_OBJECT: $this->fetchClass = isset($arg) ? $arg : 'stdClass'; break;
            case MyDBD::FETCH_COLUMN: $this->fetchCol   = isset($arg) ? $arg : 0;          break;
        }

        return $this;
    }

    /**
     * Returns the current default fetch mode.
     *
     * @return integer MyDBD::FETCH_ORDERED, MyDBD::FETCH_ASSOC,
     *                 MyDBD::FETCH_OBJECT or MyDBD::FETCH_COLUMN
     */
    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    /**
     * Get the number of fields in the result.
     *
     * @return integer the number of fields in the result.
     */
    public function getFieldCount()
    {
        return $this->result->field_count;
    }

    /**
     * Gets the number of rows in the result.
     *
     * @return integer the number of rows in the result.
     */
    public function count()
    {
        return $this->result->num_rows;
    }

    /**
     * Return the current row.
     *
     * @see setFetchMode()
     *
     * @return array|object the current row as array, assoc or object depending on the fetch mode.
     */
    public function current()
    {
        $row = $this->next();
        $this->seek($this->cursor - 1);
        return $row;
    }

    /**
     * Return the current row number.
     *
     * @return integer the current row number.
     */
    public function key()
    {
        return $this->cursor;
    }

    /**
     * Return the next row.
     *
     * @see setFetchMode()
     *
     * @return array|object the next row as array, assoc or object depending on the fetch mode.
     */
    public function next($mode = null)
    {
        $this->cursor++;

        switch(isset($mode) ? $mode : $this->fetchMode)
        {
            case MyDBD::FETCH_ORDERED: return $this->fetchArray();
            case MyDBD::FETCH_ASSOC:   return $this->fetchAssoc();
            case MyDBD::FETCH_OBJECT:  return $this->fetchObject();
            case MyDBD::FETCH_COLUMN:  return $this->fetchColumn();
        }
    }

    /**
     * Adjusts the result pointer to an arbitary row in the result.
     *
     * @param integer $position Row to seek to
     *
     * @throws OutOfBoundsException if the seek position is invalid
     *
     * @return void
     */
    public function seek($position)
    {
        if ($position < 0 && $position > $this->result->num_rows - 1)
        {
            throw new OutOfBoundsException('Invalid seek position: ' . $position);
        }

        $this->result->data_seek($this->cursor = $position);
    }

    /**
     * Seek to the first row of the result.
     *
     * @return void
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Return true if there is a current line (SPL requireemnt).
     *
     * @return boolean
     */
    public function valid()
    {
        return $this->cursor >= 0 && $this->cursor < $this->result->num_rows;
    }

    /**
     * Returns an array containing all of the result set rows.    The array format will depend on
     * the default fetch mode. You can change default fetch mode by calling setFetchMode() before
     * this method.
     *
     * <code>
     * $result = $dbh->query('SELECT * FROM table')
     *     ->setFetchMode(MyDBD_Result::FETCH_ASSOC)
     *     ->fetchAll();
     *
     * print_r($result);
     * </code>
     * <pre>
     * Array
     * (
     *     [0] => Array
     *         (
     *             [column1] => 'value_row1_column1'
     *             [column2] => 123
     *         )
     *     [1] => Array
     *     ...
     * )
     * </pre>
     *
     * <code>
     * $result = $dbh->query('SELECT * FROM table')
     *     ->setFetchMode(MyDBD_Result::FETCH_COLUMN)
     *     ->fetchAll();
     *
     * print_r($result);
     * </code>
     * <pre>
     * Array
     * (
     *     [0] => 'value_row1_column1'
     *     [1] => 'value_row2_column1'
     *     ...
     * )
     * </pre>
     *
     * @see setFetchMode()
     */
    public function fetchAll()
    {
        return iterator_to_array($this);
    }

    /**
     * Fetches the next row and returns it as a simple array.
     */
    public function fetchArray()
    {
        return $this->result->fetch_array();
    }

    /**
     * Fetches the next row and returns it as an associative array.
     */
    public function fetchAssoc()
    {
        return $this->result->fetch_assoc();
    }

    /**
     * Fetches the next row and returns it as an object.
     *
     * @param string $class Change the class to use (default stdClass).
     */
    public function fetchObject($class = null)
    {
        $class = isset($class) ? $class : $this->fetchClass;
        return $this->result->fetch_object($class);
    }

    /**
     * Returns a single column from the next row of a result set.
     *
     * @param integer|string $col Change the column to fetch. Can be either column index (starting at
     * offset 0) or field name (default 0).
     *
     * @throws OutOfBoundsException     If specified column argument isn't in the result set.
     * @throws InvalidArgumentException If specified column argument is neither string nor an integer.
     *
     * @return mixed The column data.
     */
    public function fetchColumn($col = null)
    {
        $col = isset($col) ? $col : $this->fetchCol;

        if (is_int($col))
        {
            $data = $this->fetchArray();

        }
        elseif (is_string($col))
        {
            $data = $this->fetchAssoc();
        }
        else
        {
            throw new InvalidArgumentException('Invalid column reference: . ' . $col);
        }

        if (isset($data))
        {
            if (!array_key_exists($col, $data))
            {
                throw new OutOfBoundsException('Invalid column name or index: ' . $col);
            }

            return $data[$col];
        }
        else
        {
            return null;
        }
    }

    //
    // PEAR::Db compatibility layer
    //

    public function fetchRow($fetchMode = null)
    {
        return $this->next($fetchMode);
    }

    public function fetchInto(&$row, $fetchMode = null)
    {
        $row = $this->next($fetchMode);
        return isset($row);
    }
}
