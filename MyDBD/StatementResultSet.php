<?php

/**
 * @package MyDBD
 */

/**
 * This class simulates the behavior of a normal MyDBD_Result with the result coming from
 * a prepared statement.
 *
 * @package MyDBD
 * @author Olivier Poitrey (rs@dailymotion.com)
 */
class MyDBD_StatementResultSet extends MyDBD_ResultSet
{
    private
        $metadata   = null,
        $boundData  = null,
        $fieldNames = null;

    /**
     * Note: This class shouldn't be instancied manually but using MyDBD_PreparedStatement::execute().
     *
     * @see MyDBD_PreparedStatement::execute()
     *
     * @param mysqli_stmt $result the statement handle which will be used as a result object, this
     *                            mean we can't store several MyDBD_StatementResultSet from the same
     *                            MyDBD_PreparedStatement instance (multiple execute() calls). You
     *                            treat data from the result after each execute() call. This is a
     *                            limitation from mysqli API.
     * @param mysqli_result $metadata metadata from the statment result.
     */
    public function __construct(mysqli_stmt $result, mysqli_result $metadata, array $options)
    {
        $this->result   = $result;
        $this->options  = $options;
        $this->metadata = $metadata;

        // references to $bindData array items are given to bind_result() so all subsequent calls
        // to fetch() will store results to those references.
        $this->boundData = array_fill(0, $metadata->field_count, null);
        $args = array();
        for ($i = 0; $i < count($this->boundData); $i++)
        {
            $args[$i] = &$this->boundData[$i];
        }

        call_user_func_array(array($this->result, 'bind_result'), $args);
    }

    /**
     * Reset the object so it can be reused for anoter row.
     *
     * @return $this
     */
    public function reset()
    {
        if ($this->cursor !== 0)
        {
            $this->seek(0);
        }

        $this->fetchMode  = self::FETCH_ORDERED;
        $this->fetchClass = 'stdClass';
        $this->fetchCol   = 0;

        return $this;
    }

    /**
     * @see ResultSet::fetchArray()
     */
    public function fetchArray()
    {
        return $this->result->fetch() ? $this->boundData : null;
    }

    /**
     * @see ResultSet::fetchAssoc()
     */
    public function fetchAssoc()
    {
        if ($this->result->fetch())
        {
            $assoc = array();
            $fieldNames = $this->getFieldNames();

            for ($i = 0; $i < count($this->boundData); $i++)
            {
                $assoc[$fieldNames[$i]] = $this->boundData[$i];
            }

            return $assoc;
        }
        else
        {
            return null;
        }
    }

    /**
     * @see ResultSet::fetchObject()
     */
    public function fetchObject($class = null)
    {
        if ($this->result->fetch())
        {
            $class = isset($class) ? $class : $this->fetchClass;
            $assoc = array();
            $fieldNames = $this->getFieldNames();

            for ($i = 0; $i < count($this->boundData); $i++)
            {
                $assoc[$fieldNames[$i]] = $this->boundData[$i];
            }

            if ($class == 'stdClass')
            {
                return (object) $assoc;
            }
            else
            {
                // NOTE: This is not equivalent to mysqli_fetch_object()
                return new $class($assoc);
            }
        }
        else
        {
            return null;
        }
    }

    protected function getFieldNames()
    {
        if (!isset($this->fieldNames))
        {
            $this->fieldNames = array();

            while ($field = $this->metadata->fetch_field())
            {
                $this->fieldNames[] = $field->name;
            }
        }

        return $this->fieldNames;
    }
}

?>
