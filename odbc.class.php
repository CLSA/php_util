<?php

/**
 * The database class represents a odbc connection and information.
 */

class odbc
{
  /**
   * Constructor
   *
   * The constructor either creates a new connection to a database.
   * @param string $server The name of the database's server
   * @param string $username The username to connect with.
   * @param string $password The password to connect with.
   * @throws Exception
   * @access public
   */
  public function __construct( $server, $username, $password )
  {
    $this->server = $server;
    $this->username = $username;
    $this->password = $password;
    $this->connection = odbc_connect( $this->server, $this->username, $this->password );

    if( false === $this->connection )
    {
      throw new Exception(
        sprintf( 'Unable to connect to odbc database (%s, %s)',
        odbc_errormsg(), odbc_error() ) );
    }
  }

  /**
   * Destructor
   *
   */
  public function __destruct()
  {
    odbc_close( $this->connection );
  }

  /**
   * Database convenience method.
   *
   * Execute SQL statement $sql and return true or false on success or fail.
   * @param string $sql SQL statement
   * @return true/false on success/fail
   * @access public
   */
  public function execute( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      return false;
    }
    return true;
  }

  /**
   * Database convenience method.
   *
   * Executes the SQL and returns the all the rows as a 2-dimensional array.
   * @param string $sql SQL statement
   * @return array (empty if no records are found)
   * @access public
   */
  public function get_all( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      return false;
    }
    $rows = array();
    while( odbc_fetch_row( $result ) )
    {
      $row = NULL;
      for($j = 1; $j <= odbc_num_fields( $result ); $j++ )
      {
        $field = odbc_field_name( $result, $j );
        $row[$field] = odbc_result( $result, $field );
      }
      if( null !== $row )
        $rows[] = $row;
    }
    odbc_free_result( $result );
    return $rows;
  }

  /**
   * Database convenience method.
   *
   * Executes the SQL and returns the first row as an array.
   * @param string $sql SQL statement
   * @return array (empty if no records are found)
   * @access public
   */
  public function get_row( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      return false;
    }
    $row = NULL;
    if( odbc_fetch_row( $result ) )
    {
      for( $j = 1; $j <= odbc_num_fields( $result ); $j++ )
      {
        $field = odbc_field_name( $result, $j );
        $row[$field] = odbc_result( $result, $field );
      }
    }
    odbc_free_result( $result );
    return $row;
  }

  /**
   * Database convenience method.
   *
   * Executes the SQL and returns the first field of the first row.
   * @param string $sql SQL statement
   * @return native or NULL if no records were found.
   * @access public
   */
  public function get_one( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      return false;
    }
    $array = odbc_fetch_array( $result, 0 );
    $value = is_null( $array ) ? NULL : current( $array );
    odbc_free_result( $result );
    return $value;
  }

  public function get_last_error()
  {
    return sprintf( 'odbc database error (%s, %s)', odbc_errormsg(), odbc_error() );
  }

  /**
   * A reference to the odbc resource.
   * @var resource
   * @access protected
   */
  protected $connection;

  /**
   * The server that the database is located
   * @var string
   * @access private
   */
  private $server;

  /**
   * Which username to use when connecting to the database
   * @var string
   * @access private
   */
  private $username;

  /**
   * Which password to use when connecting to the database
   * @var string
   * @access private
   */
  private $password;
}
