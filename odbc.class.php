<?php

require_once('util.class.php');

class odbc
{
  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  public function __construct( $server, $username, $password )
  {
    $this->server = $server;
    $this->username = $username;
    $this->password = $password;
    $this->connection = odbc_connect( $this->server, $this->username, $this->password );

    if( false === $this->connection )
    {
      util::error( 'Unable to connect to database, quiting [' . odbc_errormsg() . ']' );
      die();
    }
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  public function __destruct()
  {
    odbc_close( $this->connection );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  public function execute( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      util::out( odbc_errormsg() );
      util::out( $sql );
      return false;
    }
    return true;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  public function get_all( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      util::out( odbc_errormsg() );
      util::out( $sql );
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

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  public function get_row( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      util::out( odbc_errormsg() );
      util::out( $sql );
      return false;
    }
    $row = NULL;
    if( odbc_fetch_row( $result ) )
    {
      for($j = 1; $j <= odbc_num_fields( $result ); $j++ )
      {
        $field = odbc_field_name( $result, $j );
        $row[$field] = odbc_result( $result, $field );
      }
    }
    odbc_free_result( $result );
    return $row;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  public function get_one( $sql )
  {
    $result = odbc_exec( $this->connection, $sql );
    if( false === $result )
    {
      util::out( odbc_errormsg() );
      util::out( $sql );
      return false;
    }
    $array = odbc_fetch_array( $result, 0 );
    $value = is_null( $array ) ? NULL : current( $array );
    odbc_free_result( $result );
    return $value;
  }

  protected $connection;
  private $server;
  private $username;
  private $password;
}
