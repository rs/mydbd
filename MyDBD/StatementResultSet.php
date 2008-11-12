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
     * Emulate the fetchAsArray on a statement result
     */
    protected function fetchAsArray()
    {
        return $this->result->fetch() ? $this->boundData : null;
    }

    /**
     * Emulate the fetchAsAssoc on a statement result
     */
    protected function fetchAsAssoc()
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
     * Emulate the fetchAsObject on a statement result
     */
    protected function fetchAsObject()
    {
        if ($this->result->fetch())
        {
            $obj = new stdClass();

            for ($i = 0; $i < count($this->boundData); $i++)
            {
                $obj[$fieldNames[$i]] = $this->boundData[$i];
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
