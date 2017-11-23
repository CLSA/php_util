<?php

require_once('util.class.php');

class opalcurl
{
  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Class constructor.
   * @param string $opal_url
   * @param string $opal_port
   * @param string $username
   * @param string $password
   * @param string $datasource
   * @param string $view
   * @param string $jsonpath
   */
  public function __construct( $opal_url, $opal_port, $username, $password,
    $datasource='', $view='', $jsonpath='' )
  {
    $this->opal_url = $opal_url;
    $this->opal_port = $opal_port;
    $this->username = $username;
    $this->password = $password;
    $this->datasource = $datasource;
    $this->view = $view;
    $this->json_view_path = $jsonpath;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Get the working opal datasource
   * @return string
   */
  public function get_datasource() { return $this->datasource; }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Set the working opal datasource
   * @param string $datasource An opal datasource
   */
  public function set_datasource( $datasource ) { $this->datasource = $datasource; }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Get the view in the working opal datasource
   * @return string
   */
  public function get_view() { return $this->view; }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Set the view in the working opal datasource
   * @param string $view A view in the working opal datasource
   */
  public function set_view( $view ) { $this->view = $view; }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Get the path to the .json file that contains the definition of the view.
   * The json definition must contain a where attribute for magmascript entity restriction
   * by date with 'NEWDATE'
   * @return string
   */
  public function get_json_view_path() { return $this->json_view_path; }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Set the path to the .json file that contains the definition of the view
   * The json definition must contain a where attribute for magmascript entity restriction
   * by date with 'NEWDATE'
   * @param string $jsonpath A file path to the .json definition of the view
   */
  public function set_json_view_path( $jsonpath ) { $this->json_view_path = $jsonpath; }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Sends an http request to opal using the system's curl program
   * @param string $url
   * @param array $arguments An associative array of arguments to pass to curl
   * @param array $headers An associative array of headers to pass to curl
   * @return The json-decoded result of the request
   */
  private function send( $url, $arguments = array(), $headers = array() )
  {
    if( '' == $this->opal_port || null == $this->opal_port )
      $url = sprintf( 'https://%s/ws%s', $this->opal_url, $url );
    else
      $url = sprintf( 'https://%s:%d/ws%s', $this->opal_url, $this->opal_port, $url );

    $headers['Accept'] = 'application/json';
    $headers['Content-Type'] = 'application/json';
    $headers['Authorization'] =
      sprintf( 'X-Opal-Auth %s', base64_encode( sprintf( '%s:%s', $this->username, $this->password ) ) );

    $arguments['silent'] = '';
    $arguments['insecure'] = '';
    $command = sprintf( 'curl "%s"', $url );
    foreach( $headers as $name => $value )
      $command .= sprintf( ' --header "%s: %s"', $name, $value );
    foreach( $arguments as $name => $value )
      $command .= sprintf( ' --%s%s', $name, 0 < strlen( $value ) ? sprintf( ' "%s"', $value ) : '' );

    $output = '';
    $return_var = NULL;
    exec( $command, $output, $return_var );
    //var_dump($command);
    //var_dump($return_var);

    if( 0 != $return_var )
    {
      util::out( sprintf( "unable to read from opal\n  command: \"%s\"\n  returned: \"%s\"",
                      $command,
                      $return_var ) );
    }
    return 0 < count( $output ) ? json_decode( $output[0] ) : NULL;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Sends an http request to the datasource view in Opal
   * @param string $path A path to add after the view's base path
   * @param array $arguments An associative array of arguments to pass to curl
   * @param array $headers An associative array of headers to pass to curl
   * @return The json-decoded result of the request
   */
  private function send_to_view( $path = '', $arguments = array(), $headers = array() )
  {
    $url = str_replace( ' ', '%20', sprintf( '/datasource/%s/view/%s%s',
                    $this->datasource,
                    $this->view,
                    0 < strlen( $path ) ? '/'.$path : '' ) );
    return $this->send( $url, $arguments, $headers );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Returns a list of participant data in a view with variable names as keys
   * according to offset and limit symantics.
   * @param string $offset The index of the first row in the view
   * @param string $limit The number of rows to return
   * @return array of associative arrays
   */
  public function get_list( $offset = '', $limit = '' )
  {
    $path = 'valueSets';
    if( '' != $offset && '' != $limit )
      $path .=  sprintf('?offset=%s&limit=%s', $offset, $limit );
    $result = $this->send_to_view( $path );

    if( is_object( $result ) &&
        property_exists( $result, 'valueSets' ) &&
        property_exists( $result, 'variables' ) )
    {
      $variables = $result->variables;
      $valueSets = $result->valueSets;
      $result = array();
      foreach( $valueSets as $valueSetObj )
      {
        $uid = $valueSetObj->identifier;
        $data = array();
        foreach( $valueSetObj->values as $valueObj )
        {
          if( isset( $valueObj->value ) )
            $data[] = $valueObj->value;
          else
            $data[] = '';
        }
        $result[$uid] = array_combine( $variables, $data );
      }
    }
    else
    {
      $result = array();
    }
    return $result;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Sets the date to restrict downloading to in the opal view by altering its
   * magmascript entity filter.
   * The view definition .json file and the path to the file must be specified.
   * See set_json_view_path, get_json_view_path
   * @param string $date The date in YYYY-MM-DD format
   */
  public function set_date( $date )
  {
    $data = sprintf( "`sed -e 's/NEWDATE/%s/' %s/" . $this->view . ".json`",
      $date, $this->json_view_path );
    $arguments = array(
      'request' => 'PUT',
      'data' => $data );
    $this->send_to_view( '', $arguments );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Returns a list of participant identifiers in a view
   * @return array of identifiers
   */
  public function get_identifiers()
  {
    $result = $this->send_to_view( 'entities' );
    $list = array();
    foreach( $result as $obj )
    {
      if( is_object( $obj ) && property_exists( $obj, 'identifier' ) )
        $list[] = $obj->identifier;
    }
    return $list;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Returns a list of datasources (projects) in the opal instance
   * as keys with array values of table and / or view names
   * @return array of datasource name keys and table / view values
   */
  public function get_datasources()
  {
    $result = $this->send( '/datasources' );
    $list = array();
    foreach( $result as $obj )
    {
      if( is_object( $obj ) && property_exists( $obj, 'name' ) )
      {
        if( property_exists( $obj, 'table' ) )
          $list[$obj->name]['table'] = $obj->table;
        if( property_exists( $obj, 'view' ) )
          $list[$obj->name]['view'] = $obj->view;
        if( !array_key_exists( $obj->name, $list) )
          $list[$obj->name] = null;
      }
    }
    return $list;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Returns a list of tables in the class instance datasource
   * @return array of table names
   */
  public function get_tables()
  {
    $url = sprintf( '/datasource/%s/tables', $this->datasource );
    $result = $this->send( $url );
    $list = array();
    foreach( $result as $obj )
    {
      if( is_object( $obj ) && property_exists( $obj, 'name' ) )
      {
        if( property_exists( $obj, 'entityType' ) )
          $list[$obj->name]['entityType'] = $obj->entityType;
        if( property_exists( $obj, 'datasourceName' ) )
          $list[$obj->name]['datasourceName'] = $obj->datasourceName;
      }
    }
    return $list;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Returns a view definition in json decoded format
   * @return object json decoded view definition
   */
  public function get_view_definition()
  {
    $url = sprintf( '/datasource/%s/view/%s', $this->datasource, $this->view );
    return $this->send( $url );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Returns a view definition in json decoded format
   * @return object json decoded view definition
   */
  public function set_view_definition( $json )
  {
    $arguments = array(
      'request' => 'PUT',
      'data' => $json );
    $this->send_to_view( '', $arguments );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Creates a view from a json file containing a view definition.
   * The datasource must pre-exist on the Opal host.
   * @param json file
   */
  public function create_view_definition_from_jsonfile( $filename )
  {
    $url = sprintf( '/datasource/%s/views', $this->datasource );
    $arguments = array(
      'request' => 'POST',
      'data-binary' => sprintf( '@%s', $filename ) );
    $this->send( $url, $arguments );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Overwrites a view from a json file containing a view definition
   * @param json file
   */
  public function set_view_definition_from_jsonfile( $filename )
  {
    $arguments = array(
      'request' => 'PUT',
      'data-binary' => sprintf( '@%s', $filename ) );
    $this->send_to_view( '', $arguments );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Returns a list of participant data in a view with variable names as keys.
   * @param string $limit The number of rows in the view to request per iteration
   * @return array of associative arrays
   */
  public function get_complete_list( $limit = '500' )
  {
    $identifier_list = $this->get_identifiers();
    $list = array();
    $max_limit = count($identifier_list);
    $offset = 0;
    while( $offset < $max_limit )
    {
      $result = $this->get_list( $offset, $limit);
      $num = count( $result );
      if( 0 < $num )
      {
        $list = array_merge( $list, $result );
        $offset = count( $list );
      }
      else
      {
        $offset = $max_limit;
      }
    }
    return $list;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-
  /**
   * Returns a the value set from the baseline view for a particular participant
   * @param string $uid articipant identifier
   * @return The json-decoded result of the request
   */
  public function get_participant( $uid )
  {
    $result = $this->send_to_view( sprintf( 'valueSet/%s', $uid ) );
    return is_object( $result ) && property_exists( $result, 'valueSets' ) ?
      current( $result->valueSets ) : NULL;
  }

  private $opal_url;
  private $opal_port;
  private $username;
  private $password;
  private $datasource;
  private $view;
  private $json_view_path;
}
