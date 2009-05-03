MyDBD is a wrapper around mysqli compatible with PEAR::Db and inspired from DBI API. This
is not an abstraction layer meant to handle several type of databases, thus the abstraction
code overhead is very low.

### Example Usage

    $dbh = new MyDBD('hostname' => 'localhost'));
    $res = $dbh->query('SELECT field1, field2 FROM table WHERE foo = ?', array('bar'));
    
    foreach ($res as $row)
    {
        printf("field1: %s, field2: %s\n", $row[0], $row[1]);
    }
    
    $res->setFetchMode(MyDBD_ResultSet::FETCHMODE_ASSOC);
    
    foreach ($res as $row)
    {
        printf("field1: %s, field2: %s\n", $row['field1'], $row['field2']);
    }
    
    $sth = $dbh->prepare('INSERT INTO table (field1, field2) VALUES(?, ?)');
    
    foreach ($myData as $row)
    {
        $sth->execute($row[0], $row[1]);
    }

