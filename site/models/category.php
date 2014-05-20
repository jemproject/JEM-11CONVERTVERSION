<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/eventslist.php';

/**
 * Model-Category
 */
class JemModelCategory extends JemModelEventslist
{
	protected $_id			= null;
	protected $_data		= null;
	protected $_childs		= null;
	protected $_category	= null;
	//protected $_pagination	= null;
	protected $_item		= null;
	protected $_articles	= null;
	protected $_siblings	= null;
	protected $_children	= null;
	protected $_parent	= null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$app			= JFactory::getApplication();
		$jemsettings	= JEMHelper::config();
		$itemid			= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);

		// Get the parameters of the active menu item
		$params 	= $app->getParams();

		if (JRequest::getInt('id')) {
			$id = JRequest::getInt('id');
		} else {
			$id = $params->get('id', 1);
		}

		$this->setId((int)$id);

		parent::__construct();
	}

	/**
	 * Set Date
	 */
	function setdate($date)
	{
		$this->_date = $date;
	}

	/**
	 * Method to set the category id
	 */
	function setId($id)
	{
		// Set new category ID and wipe data
		$this->_id			= $id;
		$this->_data		= null;
	}

	/**
	 * set limit
	 * @param int value
	 */
	function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}



	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initiliase variables.
		$app			= JFactory::getApplication('site');
		$jemsettings	= JemHelper::config();
		$jinput         = JFactory::getApplication()->input;
		$task           = $jinput->get('task','','cmd');
		$itemid			= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);
		$pk				= JRequest::getInt('id');

		$this->setState('category.id', $pk);

		$this->setState('filter.req_catid',$pk);

		// Load the parameters. Merge Global and Menu Item params into new object
		$params = $app->getParams();
		$menuParams = new JRegistry;

		if ($menu = $app->getMenu()->getActive()) {
			$menuParams->loadString($menu->params);
		}

		$mergedParams = clone $menuParams;
		$mergedParams->merge($params);

		$this->setState('params', $mergedParams);
		$user		= JFactory::getUser();
		// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);
		$groups	= implode(',', $user->getAuthorisedViewLevels());

		# limit/start
		$value	= $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
		$this->setState('list.limit', $value);

		$value = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
		$this->setState('list.start', $value);

		# Search - variables
		$search = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search);

		$filtertype = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_type', 'filter_type', '', 'int');
		$this->setState('filter.filter_type', $filtertype);

		# publish state
		if ($task == 'archive') {
			$this->setState('filter.published',2);
		} else {
			$this->setState('filter.published',1);
		}

		###########
		## ORDER ##
		###########

		$filter_order		= $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		if($task == 'archive' && $filter_order == 'a.dates') {
			$filter_order_DirDefault = 'DESC';
		}
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order == 'a.dates') {
			$orderby = array('a.dates '.$filter_order_Dir,'a.times '.$filter_order_Dir);
		} else {
			$orderby = $filter_order . ' ' . $filter_order_Dir;
		}

		$this->setState('filter.orderby',$orderby);

		$this->setState('filter.access', true);
	}

	/**
	 * Get the events in the category
	 */
	function getItems()
	{

		$params = clone $this->getState('params');
		$items	= parent::getItems();

		if ($items) {
			foreach ($items as &$item)
			{

			}

			return $items;
		}

		return array();
	}


	/**
	 * Method to get category data for the current category
	 *
	 * @param	int		An optional ID
	 */
	public function getCategory()
	{
		if (!is_object($this->_item)) {
			if( isset( $this->state->params ) ) {
				$params = $this->state->params;
				$options = array();
				$options['countItems'] = $params->get('show_cat_num_articles', 1) || !$params->get('show_empty_categories_cat', 0);
			}
			else {
				$options['countItems'] = 0;
			}

			$categories = new JEMCategories($this->getState('category.id', 'root'));
			$this->_item = $categories->get($this->getState('category.id', 'root'));

			// Compute selected asset permissions.
			if (is_object($this->_item)) {
				$user	= JFactory::getUser();
				$userId	= $user->get('id');
				$asset	= 'com_jem.category.'.$this->_item->id;

				// Check general create permission.
				if ($user->authorise('core.create', $asset)) {
					$this->_item->getParams()->set('access-create', true);
				}


				$this->_children = $this->_item->getChildren();

				$this->_parent = false;

				if ($this->_item->getParent()) {
					$this->_parent = $this->_item->getParent();
				}

				$this->_rightsibling = $this->_item->getSibling();
				$this->_leftsibling = $this->_item->getSibling(false);
			}
			else {
				$this->_children = false;
				$this->_parent = false;
			}
		}

		return $this->_item;
	}


	/**
	 * @return	JDatabaseQuery
	 */
	function getListQuery()
	{
		$params  = $this->state->params;
		$jinput  = JFactory::getApplication()->input;
		$task    = $jinput->get('task','','cmd');

		// Create a new query object.
		$query = parent::getListQuery();

		// here we can extend the query of the Eventslist model
		return $query;
	}

	/**
	 * Get the parent categorie.
	 */
	public function getParent()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_parent;
	}

	/**
	 * Get the left sibling (adjacent) categories.
	 */
	function &getLeftSibling()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_leftsibling;
	}

	/**
	 * Get the right sibling (adjacent) categories.
	 */
	function &getRightSibling()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_rightsibling;
	}

	/**
	 * Get the child categories.
	 */
	function &getChildren()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		// Order subcategories
		if (sizeof($this->_children)) {
			$params = $this->getState()->get('params');
			if ($params->get('orderby_pri') == 'alpha' || $params->get('orderby_pri') == 'ralpha') {
				jimport('joomla.utilities.arrayhelper');
				JArrayHelper::sortObjects($this->_children, 'title', ($params->get('orderby_pri') == 'alpha') ? 1 : -1);
			}
		}

		return $this->_children;
	}
}
?>