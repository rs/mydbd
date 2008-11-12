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
    const
        FETCHMODE_ORDERED = 1,
        FETCHMODE_ASSOC   = 2,
        FETCHMODE_OBJECT  = 3;

    protected
        $result  = null,
        $options = null;

    private
        $cursor     = 0,
        $fetchMode  = self::FETCHMODE_ORDERED,
        $fetchClass = null;

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
     * @param integer $mode Ether MyDBD_ResultSet::FETCHMODE_ORDERED, MyDBD_ResultSet::FETCHMODE_ASSOC
     *                      or MyDBD_ResultSet::FETCHMODE_OBJECT.
     *
     * - FETCHMODE_ORDERED: Result is stored in an array of string with numerical keys in the order
     *                      of the fields of the query.
     * - FETCHMODE_ASSOC:   Result is stored in an associative array with keys named like fields of
     *                      the query
     * - FETCHMODE_OBJECT:  Result is stored in properties of an object.
     *
     * @param string $class If mode is MyDBD_ResultSet::FETCHMODE_OBJECT, this parameter will change
     *                      the class used to create the object. If not provided, stdClass is used
     *                      by default.
     */
    public function setFetchMode($mode, $class = 'stdClass')
    {
        if ($mode !== self::FETCHMODE_ORDERED && $mode !== self::FETCHMODE_ASSOC && $mode !== self::FETCHMODE_OBJECT)
        {
            throw InvalidArgumentException('Invalid fetch mode: ' . $mode);
        }

        $this->fetchMode = $mode;

        if ($mode == self::FETCHMODE_OBJECT)
        {
            $this->fetchClass = $class;
        }
    }

    /**
     * Returns the current default fetch mode.
     *
     * @return integer MyDBD_ResultSet::FETCHMODE_ORDERED, MyDBD_ResultSet::FETCHMODE_ASSOC or
     *                 MyDBD_ResultSet::FETCHMODE_OBJECT
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
            case self::FETCHMODE_ORDERED: return $this->fetchAsArray();
            case self::FETCHMODE_ASSOC:   return $this->fetchAsAssoc();
            case self::FETCHMODE_OBJECT:  return $this->fetchAsObject();
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

    protected function fetchAsArray()
    {
        return $this->result->fetch_array();
    }

    protected function fetchAsAssoc()
    {
        return $this->result->fetch_assoc();
    }

    protected function fetchAsObject()
    {
        return $this->result->fetch_object();
    }

    // PEAR::Db compatibility layer
    public function __call($method, $arguments)
    {
        if ($this->options['pear_compat'])
        {
            return call_user_func_array(array($this->options['pear_compat_class'], $method), array_merge(array($this), $arguments));
        }
        else
        {
            throw new BadFunctionCallException('Invalid method call: ' . $method);
        }
    }
}
