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

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component attendees Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelAttendees extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_event = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Events id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();;

		$limit		= $app->getUserStateFromRequest( 'com_jem.attendees.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.attendees.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		//set unlimited if export or print action | task=export or task=print
		$this->setState('unlimited', JRequest::getString('task'));

		$id = JRequest::getInt('id');
		$this->setId($id);


	}

	/**
	 * Method to set the category identifier
	 *
	 * @access	public
	 * @param	int Category identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	    = $id;
		$this->_data 	= null;
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();

			if ($this->getState('unlimited') == '') {
				$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			} else {
				$this->_data = $this->_getList($query);
			}
		}

		return $this->_data;
	}

	/**
	 * Method to get the total nr of the attendees
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to build the query for the attendees
	 *
	 * @access private
	 * @return integer
	 * @since 0.9
	 */
	function _buildQuery()
	{
		// Get the ORDER BY clause for the query
		$orderby	= $this->_buildContentOrderBy();
		$where		= $this->_buildContentWhere();

		$query = 'SELECT r.*, u.username, u.name, u.email'
		. ' FROM #__jem_register AS r'
		. ' LEFT JOIN #__jem_events AS a ON r.event = a.id'
		. ' LEFT JOIN #__users AS u ON r.uid = u.id'
		. $where
		. $orderby
		;

		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the attendees
	 *
	 * @access private
	 * @return integer
	 * @since 0.9
	 */
	function _buildContentOrderBy()
	{
		$app =  JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order', 		'filter_order', 	'u.username', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		
		
		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', u.name';

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the attendees
	 *
	 * @access private
	 * @return string
	 * @since 0.9
	 */
	function _buildContentWhere()
	{
		$app =  JFactory::getApplication();

		$filter 			= $app->getUserStateFromRequest( 'com_jem.attendees.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.attendees.search', 'search', '', 'string' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );
		$filter_waiting	= $app->getUserStateFromRequest( 'com_jem.attendees.waiting',	'filter_waiting',	0, 'int' );

		$where = array();

		$where[] = 'r.event = '.$this->_id;
		if ($filter_waiting) {
			$where[] = ' (a.waitinglist = 0 OR r.waiting = '.($filter_waiting-1).') ';
		}

		/*
		* Search name
		*/
		if ($search && $filter == 1) {
			$where[] = ' LOWER(u.name) LIKE \'%'.$search.'%\' ';
		}

		/*
		* Search username
		*/
		if ($search && $filter == 2) {
			$where[] = ' LOWER(u.username) LIKE \'%'.$search.'%\' ';
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}

	/**
	 * Get event data
	 *
	 * @access public
	 * @return object
	 * @since 0.9
	 */
	function getEvent()
	{
		$query = 'SELECT id, title, dates, maxplaces, waitinglist FROM #__jem_events WHERE id = '.$this->_id;

		$this->_db->setQuery( $query );

		$_event = $this->_db->loadObject();

		return $_event;
	}

	/**
	 * Delete registered users
	 *
	 * @access public
	 * @return true on success
	 * @since 0.9
	 */
	function remove($cid = array())
	{
		if (count( $cid ))
		{
			$user = implode(',', $cid);
			
			$query = 'DELETE FROM #__jem_register WHERE id IN ('. $user .') ';

			$this->_db->setQuery( $query );

			if (!$this->_db->query()) {
				JError::raiseError( 1001, $this->_db->getErrorMsg() );
			}
		}
		return true;
	}
}
?>