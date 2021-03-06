<?php

class opalcurl
{
  /**
   * Class constructor.
   * @param string $opal_url
   * @param string $opal_port
   * @param string $username
   * @param string $password
   * @param string $datasource
   * @param string $view
   * @param string $path
   */
  public function __construct( $opal_url, $opal_port, $username, $password,
    $datasource='', $view='', $path='' )
  {
    $this->opal_url = $opal_url;
    $this->opal_port = $opal_port;
    $this->username = $username;
    $this->password = $password;
    $this->datasource = $datasource;
    $this->view = $view;
    $this->json_view_path = $path;
    $this->view_is_table = false;
  }

  /**
   * Download a binary file to the specified output file name.
   * @return boolean Success or failed download
   */
  public function get_binary_file( $uid, $opal_var, $output_file )
  {
    $res = $this->get_participant( $uid );
    if( is_object( $res ) && property_exists( $res, 'values' ) )
    {
      $res = array_filter( $res->values,
        function ( $obj ) use( $opal_var )
        {
          return ( property_exists( $obj, 'link' ) &&
                   property_exists( $obj, 'length' ) &&
                   0 < $obj->length &&
                   false !== strpos( $obj->link, $opal_var ) );
        } );
    }
    if( NULL === $res || false === $res || empty($res) )
    {
      return false;
    }

    $res = current( $res );
    $link = $res->link;

    $this->send( $link, array( 'output' => $output_file ) );

    // verify the file is non-empty
    if( !file_exists( $output_file ) || 0 == filesize( $output_file ) )
    {
      return false;
    }
    return true;
  }

  /**
   * Helper function to recursively find the link to a repeated binary file
   * variable
   * @return
   */
  private function walk_recursive($obj, &$item, $opal_var, $pos)
  {
    if(is_object($obj) && NULL === $item)
    {
      if(property_exists($obj, 'values'))
      {
        foreach($obj->values as $value)
        {
          self::walk_recursive($value, $item, $opal_var, $pos);
        }
      }
      else if (
        property_exists( $obj, 'link' ) &&
        property_exists( $obj, 'length' ) &&
        0 < $obj->length &&
        false !== strpos( $obj->link, $opal_var ) &&
        false !== strpos( $obj->link, sprintf('value?pos=%d',$pos)) &&
        $item != $obj->link )
      {
        $item = $obj->link;
      }
    }
  }

  /**
   * Download a binary file to the specified output file name.
   * For repeated variables, a specific offset must be given.
   * @return boolean Success or failed download
   */
  public function get_binary_file_at_position( $uid, $opal_var, $output_file, $pos )
  {
    $res = NULL;
    self::walk_recursive( $this->get_participant( $uid ), $res, $opal_var, $pos);

    if( NULL === $res || false === $res || empty($res) )
    {
      return false;
    }

    $this->send( $res, array( 'output' => $output_file ) );

    // verify the file is non-empty
    if( !file_exists( $output_file ) || 0 == filesize( $output_file ) )
    {
      return false;
    }
    return true;
  }

  /**
   * Get the working opal datasource
   * @return string
   */
  public function get_datasource() { return $this->datasource; }

  /**
   * Set the working opal datasource
   * @param string $datasource An opal datasource
   */
  public function set_datasource( $datasource ) { $this->datasource = $datasource; }


  /**
   * specify that the view name refers to a table (true)
   * @param boolean $_view_is_table
   */
  public function set_view_is_table( $_view_is_table)
  {
    $this->view_is_table = $_view_is_table;
  }

  /**
   * Get the view in the working opal datasource
   * @return string
   */
  public function get_view() { return $this->view; }

  /**
   * Set the view in the working opal datasource
   * @param string $view A view in the working opal datasource
   */
  public function set_view( $view ) { $this->view = $view; }

  /**
   * Get the path to the .json file that contains the definition of the view.
   * The json definition must contain a where attribute for magmascript entity restriction
   * by date with 'NEWDATE'
   * @return string
   */
  public function get_json_view_path() { return $this->json_view_path; }

  /**
   * Set the path to the .json file that contains the definition of the view
   * The json definition must contain a where attribute for magmascript entity restriction
   * by date with 'NEWDATE'
   * @param string $jsonpath A file path to the .json definition of the view
   */
  public function set_json_view_path( $jsonpath ) { $this->json_view_path = $jsonpath; }

  /**
   * Sends an http request to opal using the system's curl program
   * @param string $url
   * @param array $arguments An associative array of arguments to pass to curl
   * @param array $headers An associative array of headers to pass to curl
   * @return The json-decoded result of the request
   */
  public function send( $url, $arguments = array(), $headers = array() )
  {
    if( '' == $this->opal_port || null == $this->opal_port )
      $url = sprintf( 'https://%s/ws%s', $this->opal_url, $url );
    else
      $url = sprintf( 'https://%s:%d/ws%s', $this->opal_url, $this->opal_port, $url );

    if( !array_key_exists( 'Accept', $headers ) )
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
    if( 0 != $return_var )
    {
      fwrite( STDERR, sprintf( "unable to read from opal\n  command: \"%s\"\n  returned: \"%s\"",
                      $command,
                      $return_var ) );
    }
    return 0 < count( $output ) ? json_decode( $output[0] ) : NULL;
  }

  /**
   * Sends an http request to the datasource view in Opal
   * @param string $path A path to add after the view's base path
   * @param array $arguments An associative array of arguments to pass to curl
   * @param array $headers An associative array of headers to pass to curl
   * @return The json-decoded result of the request
   */
  public function send_to_view( $path = '', $arguments = array(), $headers = array() )
  {
    $url = str_replace( ' ', '%20', sprintf( '/datasource/%s/%s/%s%s',
                    $this->datasource,
                    ($this->view_is_table?'table':'view'),
                    $this->view,
                    0 < strlen( $path ) ? '/'.$path : '' ) );
    return $this->send( $url, $arguments, $headers );
  }

  /**
   * Returns a list of participant data in a view with variable names as keys
   * according to offset and limit symantics.
   * @param string $offset The index of the first row in the view
   * @param string $limit The number of rows to return
   * @return array of associative arrays
   */
  public function get_list( $offset = null, $limit = null )
  {
    $path = 'valueSets';
    if( null !== $offset && null !== $limit )
      $path .= sprintf('?offset=%s&limit=%s', $offset, $limit );
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

  /**
   * Sets the date range to restrict downloading to in the opal view by altering its
   * magmascript entity filter.  The filter takes the form:
   * $lastupdate().before(newValue('YYYY-MM-DD','date')).and(
   * $lastupdate().after(newValue('YYYY-MM-DD','date')))
   * The view definition .json file and the path to the file must be specified.
   * See set_json_view_path, get_json_view_path
   * @param string $date_before The date before in YYYY-MM-DD format
   * @param string $date_after The date after in YYYY-MM-DD format
   * @param boolean $from_file For resolving curl argument list too long due to large json content
   */
  public function set_date_range( $date_before, $date_after, $from_file = false )
  {
    $data = sprintf( "`sed -e 's/BEFORE_DATE/%s/;s/AFTER_DATE/%s/' %s/" . $this->view . ".json`",
      $date_before, $date_after, $this->json_view_path );
    $file = null;
    if($from_file)
    {
      $file ='/tmp/' . $this->view . '.json';
      $cmd = trim($data,'`') . ' > ' . $file;
      shell_exec($cmd);
      $data = '@' . $file;
    }
    $arguments = array(
      'request' => 'PUT',
      'data' => $data );
    $this->send_to_view( '', $arguments );
    if(null!=$file && file_exists($file))
    {
      unlink($file);
    }
  }

  /**
   * Returns a list of participant identifiers in a view
   * @return array of identifiers
   */
  public function get_identifiers()
  {
    $result = $this->send_to_view( 'entities' );
    $list = array();
    if( NULL !== $result )
    {
      foreach( $result as $obj )
      {
        if( is_object( $obj ) && property_exists( $obj, 'identifier' ) )
          $list[] = $obj->identifier;
      }
    }
    return $list;
  }

  /**
   * Returns a list of datasources (projects) in the opal instance
   * as keys with array values of table and / or view names
   * @return array of datasource name keys and table / view values
   */
  public function get_datasources()
  {
    $result = $this->send( '/datasources' );
    $list = array();
    $properties = array( 'table', 'view' );
    if( NULL !== $result )
    {
      foreach( $result as $obj )
      {
        if( is_object( $obj ) && property_exists( $obj, 'name' ) )
        {
          foreach( $properties as $key )
          {
            if( property_exists( $obj, $key ) )
              $list[$obj->name][$key] = $obj->$key;
          }
          if( !array_key_exists( $obj->name, $list) )
            $list[$obj->name] = null;
        }
      }
    }
    return $list;
  }

  /**
   * Returns a list of identifier mappings in the opal instance
   * as keys with array values of table and / or view names
   * @return array of datasource name keys and table / view values
   */
  public function get_identifiers_tables()
  {
    $result = $this->send( '/identifiers/tables' );
    $list = array();
    $properties = array( 'entityType', 'link', 'datasourceName' );
    foreach( $result as $obj )
    {
      if( is_object( $obj ) && property_exists( $obj, 'name' ) )
      {
        foreach( $properties as $key )
        {
          if( property_exists( $obj, $key ) )
            $list[$obj->name][$key] = $obj->$key;
        }
        if( !array_key_exists( $obj->name, $list) )
          $list[$obj->name] = null;
      }
    }
    return $list;
  }

  /**
   * Returns a view definition in json decoded format
   * @return object json decoded view definition
   */
  public function get_identifiers_definition( $name )
  {
    $url = sprintf( '/identifiers/table/%s', $name );
    return $this->send( $url );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Downloads an export of identifier mappings in csv format.
   * @param string $path The full opal-service path to the identifier mapping
   */
  public function export_identifiers( $entityType, $dir='' )
  {
    $path = sprintf( '/identifiers/mappings/_export?type=%s', $entityType );
    if( 0 < strlen( $dir ) && !is_dir( $dir ) ) mkdir( $dir );

    // and write the recording
    $filename = sprintf( '%s/IDs-%s.csv', $dir, $entityType );
    $this->send( $path, array( 'output' => $filename ), array( 'Accept'=>'*/*') );
  }

  /**
   * Returns a list of tables in the class instance datasource
   * @return array of table names
   */
  public function get_tables()
  {
    $url = sprintf( '/datasource/%s/tables', $this->datasource );
    $result = $this->send( $url );
    $list = array();
    $properties = array( 'entityType', 'datasourceName' );
    foreach( $result as $obj )
    {
      if( is_object( $obj ) && property_exists( $obj, 'name' ) )
      {
        foreach( $properties as $key )
        {
          if( property_exists( $obj, $key ) )
            $list[$obj->name][$key] = $obj->$key;
        }
      }
    }
    return $list;
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Returns an array of objects containing a views variables
   * @return array object
   */
  public function get_view_variables()
  {
    $url = str_replace( ' ', '%20',
       sprintf( '/datasource/%s/%s/%s/variables',
       $this->datasource,
       ($this->view_is_table?'table':'view'),
       $this->view ) );
    return $this->send( $url );
  }

  // -+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+-+#+I-
  /**
   * Returns a view definition in json decoded format
   * @return object json decoded view definition
   */
  public function get_view_definition()
  {
    $url = str_replace( ' ', '%20',
       sprintf( '/datasource/%s/%s/%s',
       $this->datasource,
       ($this->view_is_table?'table':'view'),
       $this->view ) );
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

  /**
   * Returns a list of participant data in a view with variable names as keys.
   * @param string $limit The number of rows in the view to request per iteration
   * @return array of associative arrays
   */
  public function get_complete_list( $limit = '500' )
  {
    $identifier_list = $this->get_identifiers();
    $list = array();
    $max_limit = count( $identifier_list );
    $offset = 0;
    while( $offset < $max_limit )
    {
      $result = $this->get_list( $offset, $limit );
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

  /**
   * Returns a value set from a view for a particular participant
   * @param string $uid participant identifier
   * @return The json-decoded result of the request
   */
  public function get_participant( $uid )
  {
    $result = $this->send_to_view( sprintf( 'valueSet/%s', $uid ) );
    return is_object( $result ) && property_exists( $result, 'valueSets' ) ?
      current( $result->valueSets ) : NULL;
  }

  /**
   * Returns a non-repeated variable value from a view for a particular participant
   * @param string $uid participant identifier
   * @return The json-decoded result of the request
   */
  public function get_participant_value( $uid, $variable )
  {
    $result = $this->send_to_view( sprintf( 'valueSet/%s/variable/%s', $uid, $variable ) );
    return is_object( $result ) && property_exists( $result, 'value' ) ?
      $result->value : NULL;
  }

  /**
   * The url to an opal instance
   * @var string
   * @access private
   */
  private $opal_url;

  /**
   * The port of an opal instance
   * @var string
   * @access private
   */
  private $opal_port;

  /**
   * An opal instance user account name
   * @var string
   * @access private
   */
  private $username;

  /**
   * An opal instance user account password
   * @var string
   * @access private
   */
  private $password;

  /**
   * An opal datasource (project name)
   * @var string
   * @access private
   */
  private $datasource;

  /**
   * A view within an opal datasource
   * @var string
   * @access private
   */
  private $view;

  /**
   *  Specify that a view name is a table name
   * @var boolean
   * @access private
   */
  private $view_is_table;

  /**
   * Path where views in json format are stored
   * @var string
   * @access private
   */
  private $json_view_path;
}
