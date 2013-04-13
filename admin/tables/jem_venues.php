<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

/**
 * JEM venues Model class
 *
 * @package JEM
 * @since 0.9
 */
class jem_venues extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var string */
	var $venue 				= '';
	/** @var string */
	var $alias	 			= '';
	/** @var string */
	var $url 				= '';
	/** @var string */
	var $street 			= '';
	/** @var string */
	var $plz 				= '';
	/** @var string */
	var $city 				= '';
	/** @var string */
	var $state				= '';
	/** @var string */
	var $country			= '';
  	/** @var float */
  	var $latitude      		= null;
  	/** @var float */
  	var $longitude     		= null;
	/** @var string */
	var $locdescription 	= null;
	/** @var string */
	var $meta_description 	= '';
	/** @var string */
	var $meta_keywords		= '';
	/** @var string */
	var $locimage 			= '';
	/** @var int */
	var $map		 		= null;
	/** @var int */
	var $created_by			= null;
	/** @var string */
	var $author_ip	 		= null;
	/** @var date */
	var $created		 	= null;
	/** @var date */
	var $modified 			= 0;
	/** @var int */
	var $modified_by 		= null;
	/** @var int */
	var $version	 		= null;
	/** @var int */
	var $published	 		= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time 	= 0;
	/** @var int */
	var $ordering 			= null;

	function jem_venues(& $db) {
		parent::__construct('#__jem_venues', 'id', $db);
	}

	// overloaded check function
	//function check($elsettings)
	function check()
	
	{
		// not typed in a venue name
		if(!trim($this->venue)) {
	      	$this->_error = JText::_( 'ADD VENUE');
	      	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
	       	return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->venue);

		if(empty($this->alias) || $this->alias === $alias ) {
			$this->alias = $alias;
		}

		if ( $this->map ){
			if ( !trim($this->street) || !trim($this->city) || !trim($this->country) || !trim($this->plz) ) {
				if (( !trim($this->latitude) && !trim($this->longitude))) {
					$this->_error = JText::_( 'ERROR ADDRESS');
					JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
					return false;
				}
			}
		}
		
		if (JFilterInput::checkAttribute(array ('href', $this->url))) {
			$this->_error = JText::_( 'ERROR URL WRONG FORMAT' );
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
			return false;
		}

		if (trim($this->url)) {
			$this->url = strip_tags($this->url);
			$urllength = strlen($this->url);

			if ($urllength > 199) {
      			$this->_error = JText::_( 'ERROR URL LONG' );
      			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      			return false;
			}
			if (!preg_match( '/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}'
       		.'((:[0-9]{1,5})?\/.*)?$/i' , $this->url)) {
				$this->_error = JText::_( 'ERROR URL WRONG FORMAT' );
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
				return false;
			}
		}

		$this->street = strip_tags($this->street);
		$streetlength = JString::strlen($this->street);
		if ($streetlength > 50) {
     	 	$this->_error = JText::_( 'ERROR STREET LONG' );
     	 	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
     	 	return false;
		}

		$this->plz = strip_tags($this->plz);
		$plzlength = JString::strlen($this->plz);
		if ($plzlength > 10) {
      		$this->_error = JText::_( 'ERROR ZIP LONG' );
      		JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      		return false;
		}

		$this->city = strip_tags($this->city);
		$citylength = JString::strlen($this->city);
		if ($citylength > 50) {
    	  	$this->_error = JText::_( 'ERROR CITY LONG' );
    	  	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
    	  	return false;
		}

		$this->state = strip_tags($this->state);
		$statelength = JString::strlen($this->state);
		if ($statelength > 50) {
    	  	$this->_error = JText::_( 'ERROR STATE LONG' );
    	  	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
    	  	return false;
		}

		$this->country = strip_tags($this->country);
		$countrylength = JString::strlen($this->country);
		if ($countrylength > 2) {
     	 	$this->_error = JText::_( 'ERROR COUNTRY LONG' );
     	 	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
     	 	return false;
		}
		
		/** check for existing name */
/*		$query = 'SELECT id FROM #__jem_venues WHERE venue = '.$this->_db->Quote($this->venue);
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('VENUE NAME ALREADY EXIST', $this->venue));
			return false;
		}
*/
		
		return true;
	}
	
	
	  /**
   * try to insert first, update if fails
   *
   * Can be overloaded/supplemented by the child class
   *
   * @access public
   * @param boolean If false, null object variables are not updated
   * @return null|string null if successful otherwise returns and error message
   */
  function insertIgnore( $updateNulls=false )
  {
    $k = $this->_tbl_key;

    $ret = $this->_insertIgnoreObject( $this->_tbl, $this, $this->_tbl_key );
    if( !$ret )
    {
      $this->setError(get_class( $this ).'::store failed - '.$this->_db->getErrorMsg());
      return false;
    }
    return true;
  }

  /**
   * Inserts a row into a table based on an objects properties, ignore if already exists
   *
   * @access  public
   * @param string  The name of the table
   * @param object  An object whose properties match table fields
   * @param string  The name of the primary key. If provided the object property is updated.
   * @return int number of affected row
   */
  function _insertIgnoreObject( $table, &$object, $keyName = NULL )
  {
    $fmtsql = 'INSERT IGNORE INTO '.$this->_db->nameQuote($table).' ( %s ) VALUES ( %s ) ';
    $fields = array();
    foreach (get_object_vars( $object ) as $k => $v) {
      if (is_array($v) or is_object($v) or $v === NULL) {
        continue;
      }
      if ($k[0] == '_') { // internal field
        continue;
      }
      $fields[] = $this->_db->nameQuote( $k );
      $values[] = $this->_db->isQuoted( $k ) ? $this->_db->Quote( $v ) : (int) $v;
    }
    $this->_db->setQuery( sprintf( $fmtsql, implode( ",", $fields ) ,  implode( ",", $values ) ) );
    if (!$this->_db->query()) {
      return false;
    }
    $id = $this->_db->insertid();
    if ($keyName && $id) {
      $object->$keyName = $id;
    }
    return $this->_db->getAffectedRows();
  }
	
	
	
	
	
	
	
}
?>