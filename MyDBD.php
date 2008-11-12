<?php

/**
 * @package MyDBD
 */

/**
 * MyDBD is a wrapper around mysqli compatible with PEAR::Db and inspired from DBI API. This
 * is not an abstraction layer meant to handle several type of database, thus the abstraction code
 * overhead is very low.
 *
 * Example usage:
 *
 * <code>
 * $dbh = new MyDBD('hostname' => 'localhost'));
 * $res = $dbh->query('SELECT field1, field2 FROM table WHERE foo = ?', array('bar'));
 *
 * foreach ($res as $row)
 * {
 *     printf("field1: %s, field2: %s\n", $row[0], $row[1]);
 * }
 *
 * $res->setFetchMode(MyDBD_ResultSet::FETCHMODE_ASSOC);
 *
 * foreach ($res as $row)
 * {
 *     printf("field1: %s, field2: %s\n", $row['field1'], $row['field2']);
 * }
 *
 * $sth = $dbh->prepare('INSERT INTO table (field1, field2) VALUES(?, ?)');
 *
 * foreach ($myData as $row)
 * {
 *     $sth->execute($row[0], $row[1]);
 * }
 * </code>
 *
 * @package MyDBD
 * @author Olivier Poitrey (rs@dailymotion.com)
 */
class MyDBD
{
    protected
        $options                = null;

    private
        $link                   = null,
        $connected              = false,
        $connectionInfo         = null,
        $lastQueryHandle        = null,
        $extendedConnectionInfo = array(),
        $extendedQueryInfo      = array(),
        $replicationDelay       = -1,
        $realtime               = null,
        $engines                = null;

    /**
     * MyDBD constructor will init a new mysqli handle ready to be connected to a database.
     * The constructor doesn't actually connect to the database, a call to connect() have to be made.
     * You can also call any other method like query(), they will init a new connection if the handle
     * isn't yet connected. This allow easy implementation of the lasy connect model.
     *
     * Available $connectionInfo keys:
     * - hostname: Can be either a host name or an IP address. Passing the NULL value or the string
     *             "localhost" to this parameter, the local host is assumed. When possible, pipes
     *             will be used instead of the TCP/IP protocol.
     * - username: The MySQL user name.
     * - password: If provided or NULL, the MySQL server will attempt to authenticate the user
     *             against those user records which have no password only. This allows one username
     *             to be used with different permissions (depending on if a password as provided or not).
     * - database: If provided will specify the default database to be used when performing queries.
     * - port:     Specifies the port number to attempt to connect to the MySQL server.
     * - socket:   Specifies the socket or named pipe that should be used.
     *
     * Available options:
     * - compression:         Use compression protocol (bool)
     * - ssl:                 Use SSL (encryption)
     * - found_rows:          Return number of matched rows, not the number affected rows (bool)
     * - ignore_space:        Allow spaces after function names. Makes all function names reserved
     *                        words (bool)
     * - readonly:            The connection will be considerated readonly, all attempts to perform
     *                        a write query (INSERT, DELETE...) will throw an exception. (bool)
     * - query_log:           If query_log is on, all queries will be logged using MyDBD_Logger. (bool)
     * - pear_compat:         Activate the PEAR Db compatibility layer. (bool)
     * - pear_compat_class:   Change the default PEAR Db compatibility class. (string)
     * - client_interactive:  Allow interactive_timeout seconds (instead of wait_timeout seconds)
     *                        of inactivity before closing the connection. (bool)
     * - connect_timeout:     Connection timeout in seconds (int)
     * - wait_timeout:        The number of seconds the server waits for activity on a non-interactive
     *                        connection before closing it. This timeout applies only to TCP/IP and
     *                        Unix socket file connections, not to connections made via named pipes,
     *                        or shared memory. (int)
     *
     * @see MyDBD_PEARCompat for more inforation on PEAR::Db compatibility layer.
     * @see MyDBD_Logger     for more info en query_log mode.
     *
     * @param array $connectionInfo  Informations to connect to the database
     * @param array $options         Options for the connection
     *
     */
    public function __construct(array $connectionInfo = array(), array $options = array())
    {
        // lazy constructor, don't connect in the constructor
        $this->link = mysqli::init();

        $this->connectionInfo = array_merge
        (
            array
            (
                'username'              => null,
                'password'              => null,
                'hostname'              => null,
                'database'              => null,
                'port'                  => null,
                'socket'                => null,
                'flags'                 => null,
            ),
            $connectionInfo
        );

        $this->options = array_merge
        (
            array
            (
                'compression'           => false,
                'ssl'                   => false,
                'found_rows'            => false,
                'ignore_space'          => false,
                'readonly'              => false,
                'query_log'             => false,
                'pear_compat'           => false,
                'pear_compat_class'     => 'MyDBD_PearCompat',
                'connect_timeout'       => 0,
                'wait_timeout'          => 0,
                'client_interactive'    => false,
            ),
            $options
        );

        if ($this->options['compression'])        $this->connectionInfo['flags'] |= MYSQLI_CLIENT_COMPRESS;
        if ($this->options['ssl'])                $this->connectionInfo['flags'] |= MYSQLI_CLIENT_SSL;
        if ($this->options['found_rows'])         $this->connectionInfo['flags'] |= MYSQLI_CLIENT_FOUND_ROWS;
        if ($this->options['ignore_space'])       $this->connectionInfo['flags'] |= MYSQLI_CLIENT_IGNORE_SPACE;
        if ($this->options['client_interactive']) $this->connectionInfo['flags'] |= MYSQLI_CLIENT_INTERACTIVE;
        if ($this->options['connect_timeout'])    $this->link->options(MYSQLI_OPT_CONNECT_TIMEOUT, $options['connect_timeout']);
        // force loading of PEAR compatilbity layer
        if ($this->options['pear_compat'])        call_user_func(array($this->options['pear_compat_class'], 'init'));
    }

    /**
     * Open a connection to the mysql server with the provided information given at object creation.
     *
     * @throws SQLConnectFailedException on connection failure.
     *
     * @return $this
     */
    public function connect()
    {
        $success = @$this->link->real_connect
        (
            $this->connectionInfo['hostname'],
            $this->connectionInfo['username'],
            $this->connectionInfo['password'],
            $this->connectionInfo['database'],
            $this->connectionInfo['port'],
            $this->connectionInfo['socket'],
            $this->connectionInfo['flags']
        );

        if (!$success)
        {
            $error = mysqli_connect_error();
            $errorno = mysqli_connect_errno();
            throw new SQLConnectFailedException($error, $errorno);
        }

        if ($this->options['wait_timeout'])
        {
            $this->link->query('SET wait_timeout=' . $this->options['wait_timeout']);
        }

        $this->connected = true;

        return $this;
    }

    /**
     * Get the link to the database. If the link isn't connected, try to connect if the $autoconnect
     * argument is TRUE or throw an exception otherwise.
     *
     * @param boolean $autoconnect if true and connection isn't made or lost, the connection will
     *                             automatically established, otherwise, an SQLNotConnectedException
     *                             will be thrown.
     *
     * @throws SQLNotConnectedException if connection isn't established and $autoconnect is FALSE.
     *
     * @return mysqli connection handler.
     */
    protected function link($autoconnect = true)
    {
        if (!$this->connected || !$this->link->ping())
        {
            if ($autoconnect)
            {
                $this->connect();
            }
            else
            {
                throw new SQLNotConnectedException();
            }
        }

        return $this->link;
    }

    protected function handleErrors()
    {
        if ($this->link->errno)
        {
            MyDBD_Error::throwError($this->link->errno, $this->link->error, $this->link->sqlstate);
        }
    }

    /**
     * Performs a query on the database.
     *
     * @param string $query  The SQL query.
     * @param array  $params Values for placeholders of the SQL query if any. Quantity of values passed
     *                       must match quantity of placeholders in the query. If placeholders are
     *                       are used in the query, the query will be prepared and this parameter
     *                       is mandatory, otherwise, this parameter doesn't have to be given.
     *
     * @throws SQLException if an error happen
     *
     * @return MyDBD_ResultSet if no placeholder where used or MyDBD_StatementResultSet if placeholders
     *         where used and query had to be prepared.
     */
    public function query($query, $params = null)
    {
        $this->injectExtendedInfo($query);

        if ($this->options['readonly'])
        {
            $this->checkReadonlyQuery($query);
        }

        if ($this->options['query_log']) $start = microtime(true);

        if (isset($params) && (is_array($params) ? count($params) > 0 : !empty($params)))
        {
            $sth = $this->prepare($query);
            $result = call_user_func_array(array($sth, 'execute'), is_array($params) ? $params : $params);
        }
        else
        {
            $result = new MyDBD_ResultSet($this->link()->query($query), $this->options);
            $this->handleErrors();
            $this->lastQueryHandle = $this->link(); // used by affectedRows()

            if ($this->options['query_log']) MyDBD_Logger::log('query', $query, null, microtime(true) - $start);
        }

        return $result;
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
     * allowed in the VALUES()  list of an INSERT statement (to specify column values for a row),
     * or in a comparison with a column in a WHERE clause to specify a comparison value.
     * However, they are not allowed for identifiers (such as table or column names), in the select
     * list that names the columns to be returned by a SELECT statement, or to specify both operands
     * of a binary operator such as the = equal sign. The latter restriction is necessary because
     * it would be impossible to determine the parameter type. It's not allowed to compare marker
     * with NULL by ? IS NULL too. In general, parameters are legal only in Data Manipulation
     * Languange (DML) statements, and not in Data Defination Language (DDL) statements.
     *
     * @return MyDBD_PreparedStatement
     */
    public function prepare($query)
    {
        $stmt = $this->link()->stmt_init();
        $this->lastQueryHandle = $stmt; // used by affectedRows()
        $this->handleErrors();

        $sth = new MyDBD_PreparedStatement($stmt, $this->options);
        $sth->prepare($query);

        return $sth;
    }

    /**
     * Start a new transaction.
     *
     * To finish the transaction you have to call commit() to apply the changes or rollback() to
     * cancel.
     *
     * @see rollback()
     * @see commit()
     *
     * @return boolean TRUE on success, FALSE on failure.
     */
    public function begin()
    {
        return $this->link()->autocommit(false);
    }

    /**
     * Commits the current transaction.
     *
     * @return boolean TRUE on success, FALSE on failure.
     */
    public function commit()
    {
        $result = $this->link(false)->commit();
        $this->handleErrors();
        return $result;
    }

    /**
     * Rolls back current transaction.
     *
     * @return boolean TRUE on success, FALSE on failure.
     */
    public function rollback()
    {
        $result = $this->link(false)->rollback();
        $this->handleErrors();
        return $result;
    }

    /**
     * Asks the server to kill a MySQL thread.
     *
     * This method is used to ask the server to kill a MySQL thread specified by the processid
     * parameter. This value must be retrieved by calling the threadId() method.
     *
     * To stop a running query you should use the SQL command KILL QUERY processid.
     *
     * @see threadId()
     *
     * @param integer $processId
     *
     * @return boolean TRUE on success, FALSE on failure.
     */
    public function kill($processId)
    {
        $result = $this->link()->kill($processId);
        $this->handleErrors();
        return $result;
    }

    /**
     * Returns the thread ID for the current connection.
     *
     * The threadId() method returns the thread ID for the current connection which can then be
     * killed using the kill() method. If the connection is lost, the next command will reconnect
     * and the thread ID will be other. Therefore you should get the thread ID only when you need it.
     *
     * @see kill()
     *
     * @return the Thread ID for the current connection.
     */
    public function threadId()
    {
        $result = $this->link()->thread_id();
        $this->handleErrors();
        return $result;
    }

    /**
     * Pings a server connection, or tries to reconnect if the connection has gone down.
     *
     * @return boolean TRUE on success, FALSE on failure.
     */
    public function ping()
    {
        try
        {
            $result = $this->link(false)->ping();
            $this->handleErrors();
        }
        catch(SQLNotConnectedException $e)
        {
            $result = false;
        }

        return $result;
    }

    /**
     * Close connection to the database.
     *
     * @return boolean TRUE on success, FALSE on failure, NULL if the connection was already closed.
     */
    public function disconnect()
    {
        try
        {
            $result = $this->link(false)->close();
            $this->handleErrors();
        }
        catch(SQLNotConnectedException $e)
        {
            $result = null;
        }

        return $result;
    }

    /**
     * Returns the auto generated id used in the last query.
     *
     * @return integer The value of the AUTO_INCREMENT field that was updated by the previous query.
     *                 Returns zero if there was no previous query on the connection or if the query
     *                 did not update an AUTO_INCREMENT value.
     */
    public function getInsertId()
    {
        return $this->link(false)->insert_id;
    }

    /**
     * Gets the number of affected rows in a previous MySQL operation.
     *
     * Returns the number of rows affected by INSERT, UPDATE, or DELETE query. This function only
     * works with queries which update a table. In order to get the number of rows from a SELECT
     * query, use MyDBD_ResultSet::count() instead.
     *
     * Note: for backward compatibility, this method will return the affected rows from the last
     * executed statment if no query have been executed since the last call to the prepare() method.
     * ie: If you call query(), prepare() then execute(), this method won't give the number of affected
     * rows done by the query() call but the one done by the execute() call.
     *
     * @return integer An integer greater than zero indicates the number of rows affected or retrieved.
     *                 Zero indicates that no records where updated for an UPDATE statement, no rows
     *                 matched the WHERE clause in the query or that no query has yet been executed.
     *                 -1 indicates that the query returned an error.
     */
    public function getAffectedRows()
    {
        return isset($this->lastQueryHandle) ? $this->lastQueryHandle->affected_rows : 0;
    }

    /* DM Compatibility layer */

    /**
     * Set extended info for the query. The info won't be retain for the next queries. Example
     * of query level extended info is TIMEOUT => 30 to instruct mysql-genocide this query should
     * kill if execution time exceed this value.
     *
     * @param string $key   The label of the info.
     * @param string $value The value of the info, if NULL is passed, the info is suppressed.
     *
     * @return $this
     */
    public function setExtendedQueryInfo($key, $value)
    {
        if (!isset($value))
        {
            unset($this->extendedQueryInfo[$key]);
        }
        else
        {
            $this->extendedQueryInfo[$key] = $value;
        }

        return $this;
    }

    public function flushExtendedQueryInfo()
    {
        $this->extendedQueryInfo = array();
    }

    /**
     * Set extended info for the connection. The info will be retain for all future queries on this
     * connection. Example of connection level extended info is URI => /current/uri to help DBAs
     * to figure out from where the query have been generated.
     *
     *
     * @param string $key   The label of the info.
     * @param string $value The value of the info, if NULL is passed, the info is suppressed.
     *
     * @return $this
     */
    public function setExtendedConnectionInfo($key, $value = null)
    {
        if (!isset($value))
        {
            unset($this->extendedConnectionInfo[$key]);
        }
        else
        {
            $this->extendedConnectionInfo[$key] = $value;
        }
    }

    public function flushExtendedConnectionInfo()
    {
        $this->extendedConnectionInfo = array();
    }

    protected function injectExtendedInfo(&$query)
    {
        $comments = array();

        foreach (array_merge($this->extendedConnectionInfo, $this->extendedQueryInfo) as $key => $value)
        {
            if (isset($value))
            {
                $comments[] = $key . ':' . $value;
            }
            else
            {
                $comments[] = $key;
            }
        }

        $this->extendedQueryInfo = array();

        if (count($comments) > 0)
        {
            // NOTE: ensures we remove "end of comment" caracter sequence which would allow
            // all kinds of SQL injections
            $query .= ' /* ' . str_replace('*/', '*\/', implode(', ', $comments)) . ' */';
        }
    }

    /**
     * Set read-only mode on the current connection. When read-only mode is activated, all query
     * doing writes (except on temporary tables) will result to an SQLReadOnlyException exception.
     *
     * @param boolean $readonly If TRUE, activate readonly mode, disable otherwise.
     *
     * @return $this
     */
    public function setReadOnly($readonly)
    {
        $this->options['readonly'] = $readonly;
        return $this;
    }

    /**
     * Check read-only status on the current connection.
     *
     * @return boolean TRUE if activated, FALSE otherwise.
     */
    public function isReadOnly()
    {
        return $this->options['readonly'];
    }

    protected function checkReadonlyQuery($query)
    {
        if (preg_match('/^\s*(insert|delete|update|replace|create)\s/i', $query, $match))
        {
            if (preg_match('/^\s*(insert|delete|update|replace|create)\s+(from|into|table)\s+norepli_\w+/i', $query))
            {
                // we can only write in noreply_ tables
                return;
            }
            if (preg_match('/^\s*create\s+temporary\s+/i', $query))
            {
                // allow tmp tables
                return;
            }

            throw new SQLReadOnlyException("Can't send write queries on a read-only connexion: " . $query);
        }
    }

    /**
     * Change the number of second of inactivity before connection will be automatically closed. This
     * value can also be changed at initialization time using "wait_timeout" argument of the $options
     * parameter of the constructor of this classs.
     *
     * @param integer Number of seconds of inactivity to wait before close the connection.
     *
     * @return $this
     */
    public function setAutoDisconnect($autoDisconnect)
    {
        $this->query('SET wait_timeout=' . $autoDisconnect ? $autoDisconnect : 28800);
        return $this;
    }

    public function isRealtime()
    {
        if (!isset($this->realtime))
        {
            try
            {
                $delay = $this->getReplicationDelay();
            }
            catch (SQLException $e)
            {
                $delay = null; // if can't get replication delay, assume replication is down
                error_log("Error while checking real-time slave status, assuming it's not RT: ".$e->getMessage());
            }
            $this->realtime = isset($delay) && $delay == 0;
        }

        return $this->realtime;
    }

    public function getReplicationDelay()
    {
        if (!$this->isReadOnly()) // we asume we're on a master db
        {
            return 0;
        }

        if ($this->replicationDelay == -1)
        {
            $slaveInfo = $this->getRow("SHOW SLAVE STATUS", null, DB_FETCHMODE_ASSOC);

            if (isset($slaveInfo['Seconds_Behind_Master']))
            {
                $this->replicationDelay = $slaveInfo['Seconds_Behind_Master'];
            }
            else
            {
                $this->replicationDelay = null;
            }
        }

        return $this->replicationDelay;
    }

    public function hasEngine($engine)
    {
        if (!isset($this->engines))
        {
            $this->engines = array();

            $rs = $this->query('SHOW ENGINES');
            $row = null;

            while($rs->fetchInto($row))
            {
                if($row[1] == 'YES' || $row[1] == 'DEFAULT')
                {
                    $this->engines[] = strtolower($row[0]);
                }
            }
        }

        return in_array(strtolower($engine), $this->engines);
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