<?php

/**
 * @package MyDBD
 */

/**
 * @package MyDBD
 * @author Olivier Poitrey (rs@dailymotion.com)
 */
class MyDBD_PreparedStatement
{
    private
        $stmt           = null,
        $options        = null,
        $preparedQuery  = null,
        $boundParams    = null;

    /**
     * This object is meant to be created by MyDBD.
     *
     * @param mysqli_stmt $preparedStatement
     */
    public function __construct(mysqli_stmt $preparedStatement, array $options)
    {
        $this->stmt    = $preparedStatement;
        $this->options = $options;
    }

    /**
     * Prepare a SQL statement for execution. Once prepared, a statement can be executed one or several
     * time with different parameters if placeholder where used.
     *
     * @see MyDBD_PreparedStatement::prepare()
     * @see MyDBD_PreparedStatement::execute()
     *
     * @param string $query The SQL query to prepare
     *
     * This parameter can include one or more parameter markers in the SQL statement by embedding
     * question mark (?) characters at the appropriate positions.
     *
     * Note: The markers are legal only in certain places in SQL statements. For example, they are
     * allowed in the VALUES() list of an INSERT statement (to specify column values for a row),
     * or in a comparison with a column in a WHERE clause to specify a comparison value.
     * However, they are not allowed for identifiers (such as table or column names), in the select
     * list that names the columns to be returned by a SELECT statement, or to specify both operands
     * of a binary operator such as the = equal sign. The latter restriction is necessary because
     * it would be impossible to determine the parameter type. It's not allowed to compare marker
     * with NULL by ? IS NULL too. In general, parameters are legal only in Data Manipulation
     * Languange (DML) statements, and not in Data Defination Language (DDL) statements.
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function prepare($query)
    {
        if ($this->options['query_log']) $start = microtime(true);

        if ($this->stmt->prepare($query))
        {
            if ($this->options['query_log']) MyDBD_Logger::log('prepare', $query, null, microtime(true) - $start);
            $this->preparedQuery = $query;
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Executes a query previously prepared using the prepare() method.
     * When executed any parameter markers which exist will automatically be replaced with the
     * parameters passed as argument.
     *
     * If the statement is UPDATE, DELETE, or INSERT, the total number of affected rows can be
     * determined by using the MyDBD_PreparedStatement::affectedRows() method. Likewise, if the query
     * yields a result set, the MyDBD_ResultSet::next() function is used.
     *
     * @see MyDBD_PreparedStatement::prepare()
     * @see MyDBD_PreparedStatement::affectedRows()
     * @see MyDBD_StatementResultSet::next()
     *
     * @param string|integer|double $param,... parameters to replace markers of the prepared query.
     *
     * Note: the type of the parameter is important, it will be used to determine the type of binding
     * to use for the parameter. $sth->execute('42') isn't equal to $sth->execute(42).
     *
     * @throws SQLMismatchException             if the number of parameters passed isn't equal to the
     *                                          number of markers in the prepared query.
     * @throws SQLNotPreparedStatementException if no statement have been prepared before calling this
     *                                          method.
     *
     * @return MyDBD_StatementResultSet if the query yields a result set, NULL otherwise.
     */
    public function execute()
    {
        if (is_null($this->preparedQuery))
        {
            throw new SQLNotPreparedStatementException('Cannot execute an not prepared statement.');
        }

        if ($this->options['query_log']) $start = microtime(true);

        if ($this->stmt->param_count > 0)
        {
            $params = func_get_args();
            $this->bindParams($params);
        }

        $this->stmt->execute();
        $this->stmt->store_result();
        $this->handleErrors();

        if ($this->options['query_log']) MyDBD_Logger::log('execute', $this->preparedQuery, (isset($params) ? $params : null), microtime(true) - $start);

        if ($metadata = $this->stmt->result_metadata())
        {
            // integrity problem due to mysqli design limitation
            // you can't store several result set on several executed query
            // from the same prepared statment
            return new MyDBD_StatementResultSet($this->stmt, $metadata, $this->options);
        }
        else
        {
            return null;
        }
    }

    /**
     * Returns the total number of rows changed, deleted, or inserted by the last executed statement.
     *
     * Returns the number of rows affected by INSERT, UPDATE, or DELETE query. This function only
     * works with queries which update a table. In order to get the number of  rows from a SELECT
     * query, use MyDBD_StatementResultSet::count() instead.
     *
     * @return integer An integer greater than zero indicates the number of rows affected or retrieved.
     *                 Zero indicates that no records where updated for an UPDATE statement, no rows
     *                 matched the WHERE clause in the query or that no query has yet been executed.
     *                 -1 indicates that the query returned an error.
     */
    public function affectedRows()
    {
        return $this->stmt->affected_rows;
    }

    protected function bindParams($params)
    {
        if (count($params) != $this->stmt->param_count)
        {
            throw new SQLMismatchException
            (
                sprintf
                (
                    'Wrong parameter count for prepared statement: %d expected, %d given.',
                    $this->stmt->param_count,
                    count($params)
                )
            );
        }

        if (is_null($this->boundParams))
        {
            $types = '';

            foreach ($params as $param)
            {
                if (is_double($param))
                {
                    $types .= 'd';
                }
                else if (is_integer($param))
                {
                    $types .= 'i';
                }
                else if (is_string($param))
                {
                    $types .= 's';
                }
            }

            $this->boundParams = array_fill(0, $this->stmt->param_count, null);
            $args = array($this->stmt, $types);
            for ($i = 0; $i < count($this->boundParams); $i++)
            {
                $args[$i + 2] = &$this->bindedData[$i];
            }

            call_user_func_array('mysqli_stmt_bind_param', $args);

            $this->handleErrors();
        }

        for ($i = 0; $i < count($params); $i++)
        {
            $this->bindedData[$i] = $params[$i];
        }
    }

    protected function handleErrors()
    {
        if ($this->stmt->errno)
        {
            MyDBD_Error::throwError($this->stmt->errno, $this->stmt->error);;
        }
    }
}