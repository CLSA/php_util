<?php

/**
 * The database class represents a mysql database connection and information.
 */

class database
{
  /**
   * Constructor
   *
   * The constructor either creates a new connection to a database.
   * @param string $server The name of the database's server
   * @param string $username The username to connect with.
   * @param string $password The password to connect with.
   * @param string $database The name of the database.
   * @throws Exception
   * @access public
   */
  public function __construct( $server, $username, $password, $database )
  {
    $this->server = $server;
    $this->username = $username;
    $this->password = $password;
    $this->name = $database;
    $this->connection = new \mysqli( $this->server, $this->username, $this->password, $this->name );
    if( $this->connection->connect_error )
    {
      throw new Exception(
        sprintf( 'Unable to connect to database (%s, %s)',
        $this->connection->connect_error, $this->connection->connect_errno ) );
    }
    $this->connection->set_charset( 'utf8' );
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
    $result = $this->connection->query( $sql );
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
    $result = $this->connection->query( $sql );
    if( false === $result )
    {
      return false;
    }
    $rows = array();
    while( $row = $result->fetch_assoc() ) $rows[] = $row;
    $result->free();
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
    $result = $this->connection->query( $sql );
    if( false === $result )
    {
      return false;
    }
    $row = $result->fetch_assoc();
    $result->free();
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
    $result = $this->connection->query( $sql );
    if( false === $result )
    {
      return false;
    }
    $array = $result->fetch_array( MYSQLI_NUM );
    $result->free();
    $value = is_null( $array ) ? NULL : current( $array );
    return $value;
  }

  /**
   * Database convenience method.
   *
   * Returns the last autonumbering ID inserted.
   * @return int
   * @access public
   */
  public function insert_id()
  {
    $id = $this->connection->insert_id;
    return $id;
  }

  /**
   * Returns the string formatted for database queries.
   *
   * The returned value will be put in double quotes unless the input is null in which case NULL
   * is returned.
   * @param string $string The string to format for use in a query.
   * @return string
   * @access public
   */
  public function format_string( $string )
  {
    // NULL values are returned as a MySQL NULL value
    if( is_null( $string ) ) return 'NULL';

    // boolean values must be converted to strings (without double-quotes)
    if( is_bool( $string ) ) return $string ? 'true' : 'false';

    // trim whitespace from the begining and end of the string
    if( is_string( $string ) ) $string = trim( $string );

    return 0 == strlen( $string ) ?
      'NULL' : sprintf( '"%s"', $this->connection->real_escape_string( $string ) );
  }

  public function get_last_error()
  {
    if( isset( $this->connection ) )
      return sprintf( 'mysqli database error (%s, %s)',
        $this->connection->error, $this->connection->errno );
    else
      return '';
  }

  /**
   * A reference to the mysqli resource.
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

  /**
   * The name of the database.
   * @var string
   * @access private
   */
  private $name;
}
