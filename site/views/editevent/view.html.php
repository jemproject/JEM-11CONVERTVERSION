<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die();

/**
 * Editevent-View
 */
class JemViewEditevent extends JViewLegacy
{
	protected $form;
	protected $item;
	protected $return_page;
	protected $state;

	public function display($tpl = null)
	{
		if ($this->getLayout() == 'choosevenue') {
			$this->_displaychoosevenue($tpl);
			return;
		}

		if ($this->getLayout() == 'choosecontact') {
			$this->_displaychoosecontact($tpl);
			return;
		}

		// Initialise variables.
		$jemsettings = JEMHelper::config();
		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$document = JFactory::getDocument();
		$model = $this->getModel();
		$menu = $app->getMenu();
		$menuitem = $menu->getActive();
		$pathway = $app->getPathway();
		$url = JURI::root();

		// Get model data.
		$this->state = $this->get('State');
		$this->item = $this->get('Item');
		$this->params = $this->state->get('params');

		// Create a shortcut for $item and params.
		$item = $this->item;
		$params = $this->params;

		$this->form = $this->get('Form');
		$this->return_page = $this->get('ReturnPage');

		// check for guest
		if (!$user || $user->id == 0) {
			$app->enqueueMessage(JText::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		if (empty($this->item->id)) {
			// Check if the user has access to the form
			$maintainer = JemUser::ismaintainer('add');
			$genaccess  = JemUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes );

			if ($maintainer || $genaccess ) {
				$dellink = true;
			} else {
				$dellink = false;
			}

			$authorised = $user->authorise('core.create','com_jem') || (count($user->getAuthorisedCategories('com_jem', 'core.create')) || $dellink);
		} else {
			// Check if user can edit
			$maintainer5 = JemUser::ismaintainer('edit',$this->item->id);
			$genaccess5  = JemUser::editaccess($jemsettings->eventowner, $this->item->created_by, $jemsettings->eventeditrec, $jemsettings->eventedit);

			if ($maintainer5 || $genaccess5 ) {
				$allowedtoeditevent = true;
			} else {
				$allowedtoeditevent = false;
			}

			$authorised = $this->item->params->get('access-edit') || $allowedtoeditevent ;
		}

		if ($authorised !== true) {
			$app->enqueueMessage(JText::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
				&& $menuitem->query['view']   == 'editevent'
				&& 0 == $item->id); // menu item is always for new event

		$title = ($item->id == 0) ? JText::_('COM_JEM_EDITEVENT_ADD_EVENT')
		                          : JText::sprintf('COM_JEM_EDITEVENT_EDIT_EVENT', $item->title);

		if ($useMenuItemParams) {
			$pagetitle = $menuitem->title ? $menuitem->title : $title;
			$params->def('page_title', $pagetitle);
			$params->def('page_heading', $pagetitle);
			$pathway->setItemName(1, $pagetitle);

			// Load layout from menu item if one is set else from event if there is one set
			if (isset($menuitem->query['layout'])) {
				$this->setLayout($menuitem->query['layout']);
			} elseif ($layout = $item->params->get('event_layout')) {
				$this->setLayout($layout);
			}

			$item->params->merge($params);
		} else {
			$pagetitle = $title;
			$params->set('page_title', $pagetitle);
			$params->set('page_heading', $pagetitle);
			$params->set('show_page_heading', 1); // ensure page heading is shown
			$params->set('introtext', ''); // there is definitely no introtext.
			$params->set('show_introtext', 0);
			$pathway->addItem($pagetitle, ''); // link not required here so '' is ok

			// Check for alternative layouts (since we are not in a edit-event menu item)
			// Load layout from event if one is set
			if ($layout = $item->params->get('event_layout')) {
				$this->setLayout($layout);
			}

			$temp = clone($params);
			$temp->merge($item->params);
			$item->params = $temp;
		}

		if (!empty($this->item) && isset($this->item->id)) {
			// $this->item->images = json_decode($this->item->images);
			// $this->item->urls = json_decode($this->item->urls);

			$tmp = new stdClass();

			// check for recurrence
			if (($this->item->recurrence_type != 0) || ($this->item->recurrence_first_id != 0)) {
				$tmp->recurrence_type = 0;
				$tmp->recurrence_first_id = 0;
			}

			// $tmp->images = $this->item->images;
			// $tmp->urls = $this->item->urls;
			$this->form->bind($tmp);
		}

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseWarning(500, implode("\n", $errors));
			return false;
		}

		$access2      = JEMHelper::getAccesslevelOptions();
		$this->access = $access2;

		JHtml::_('behavior.formvalidation');
		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal', 'a.flyermodal');

		// add css file
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);

		// Load scripts
		JHtml::_('script', 'com_jem/attachments.js', false, true);
		JHtml::_('script', 'com_jem/recurrence.js', false, true);
		JHtml::_('script', 'com_jem/seo.js', false, true);
		JHtml::_('script', 'com_jem/unlimited.js', false, true);
		JHtml::_('script', 'com_jem/other.js', false, true);

		// Escape strings for HTML output
		$this->pageclass_sfx = htmlspecialchars($item->params->get('pageclass_sfx'));
		$this->dimage = JemImage::flyercreator($this->item->datimage, 'event');
		$this->jemsettings = $jemsettings;
		$this->infoimage = JHtml::_('image', 'com_jem/icon-16-hint.png', JText::_('COM_JEM_NOTES'), NULL, true);

		$this->user = $user;

		if ($params->get('enable_category') == 1) {
			$this->form->setFieldAttribute('catid', 'default', $params->get('catid', 1));
			$this->form->setFieldAttribute('catid', 'readonly', 'true');
		}

		$this->_prepareDocument();
		parent::display($tpl);
	}


	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		$app = JFactory::getApplication();

		$title = $this->params->get('page_title');
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}
		$this->document->setTitle($title);

		// TODO: Is it useful to have meta data in an edit view?
		//       Also shouldn't be "robots" set to "noindex, nofollow"?
		if ($this->params->get('menu-meta_description')) {
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->params->get('menu-meta_keywords')) {
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots')) {
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}
	}


	/**
	 * Creates the output for the venue select listing
	 */
	protected function _displaychoosevenue($tpl)
	{
		$app			= JFactory::getApplication();
		$jemsettings = JemHelper::config();
		$document    = JFactory::getDocument();
		$jinput      = JFactory::getApplication()->input;
		$limitstart  = $jinput->get('limitstart', '0', 'int');
		$limit       = $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $jemsettings->display_num, 'int');

		$filter_order 		= $jinput->get('filter_order', 'l.venue', 'cmd');
		$filter_order_Dir	= $jinput->get('filter_order_Dir', 'ASC', 'word');
		$filter				= $jinput->get('filter_search', '', 'string');
		$filter_type		= $jinput->get('filter_type', '', 'int');

		// Get/Create the model
		$rows = $this->get('Venues');
		$total = $this->get('Countitems');

		JHtml::_('behavior.modal', 'a.flyermodal');

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']		= $filter_order;

		$document->setTitle(JText::_('COM_JEM_SELECT_VENUE'));
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);

		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_VENUE'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_CITY'));
		$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_STATE'));
		$searchfilter = JHtml::_('select.genericlist', $filters, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type);

		$this->rows = $rows;
		$this->searchfilter = $searchfilter;
		$this->pagination = $pagination;
		$this->lists = $lists;
		$this->filter = $filter;

		parent::display($tpl);
	}


	/**
	 * Creates the output for the contact select listing
	 */
	protected function _displaychoosecontact($tpl)
	{
		$app         = JFactory::getApplication();
		$jinput      = JFactory::getApplication()->input;
		$jemsettings = JemHelper::config();
		$db          = JFactory::getDBO();
		$document    = JFactory::getDocument();

		$filter_order		= $app->getUserStateFromRequest('com_jem.contactelement.filter_order', 'filter_order', 'con.name', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.contactelement.filter_order_Dir', 'filter_order_Dir', '', 'word');
		$filter 			= $app->getUserStateFromRequest('com_jem.contactelement.filter', 'filter', '', 'int');
		$filter_state 		= $app->getUserStateFromRequest('com_jem.contactelement.filter_state', 'filter_state', '*', 'word');
		$search 			= $app->getUserStateFromRequest('com_jem.contactelement.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));
		$limitstart 		= $jinput->get('limitstart', '0', 'int');
		$limit				= $app->getUserStateFromRequest('com_jem.selectcontact.limit', 'limit', $jemsettings->display_num, 'int');

		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal', 'a.flyermodal');

		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);

		$document->setTitle(JText::_('COM_JEM_SELECT_CONTACT'));

		// Get/Create the model
		$rows	= $this->get('Contact');
		$total	= $this->get('CountContactitems');

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		//publish unpublished filter
		$lists['state'] = JHtml::_('grid.state', $filter_state);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']		= $filter_order;

		//Build search filter
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_NAME'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_ADDRESS'));
		$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_CITY'));
		$filters[] = JHtml::_('select.option', '4', JText::_('COM_JEM_STATE'));
		$searchfilter = JHtml::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter);

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->searchfilter = $searchfilter;
		$this->lists		= $lists;
		$this->rows			= $rows;
		$this->pagination	= $pagination;

		parent::display($tpl);
	}
}
?>
