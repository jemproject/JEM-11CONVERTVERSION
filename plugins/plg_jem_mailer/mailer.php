<?php
/**
 * @version 1.9.6
 * @package JEM
 * @subpackage JEM Mailer Plugin
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// Import library dependencies
jimport('joomla.event.plugin');
jimport('joomla.utilities.mail');

//Load the Plugin language file out of the administration
//JPlugin::loadLanguage( 'plg_jem_mailer', JPATH_ADMINISTRATOR);
$lang = JFactory::getLanguage();
$lang->load('plg_jem_mailer', JPATH_ADMINISTRATOR);

include_once(JPATH_SITE.'/components/com_jem/helpers/route.php');

class plgJEMMailer extends JPlugin {

	private $_SiteName = '';
	private $_MailFrom = '';
	private $_FromName = '';
	private $_receivers = array();

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 *
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();

		$app = JFactory::getApplication();
		$db = JFactory::getDBO();

		$this->_SiteName 	= $app->getCfg('sitename');
		$this->_MailFrom	= $app->getCfg('mailfrom');
		$this->_FromName 	= $app->getCfg('fromname');

		if( $this->params->get('fetch_admin_mails', '0') ) {
			//get list of admins who receive system mails
			$query = 'SELECT id, email, name' 
					.' FROM #__users'
					.' WHERE sendEmail = 1';
			$db->setQuery($query);

			if (!$db->query()) {
				JError::raiseError( 500, $db->stderr(true));
				return;
			}

			$admin_mails 		= $db->loadColumn(1);
			$additional_mails 	= explode( ',', trim($this->params->get('receivers')));
			$this->_receivers	= array_merge($admin_mails, $additional_mails);

		} else {
			$this->_receivers	= explode( ',', trim($this->params->get('receivers')));
		}
	}

	/**
	 * This method handles any mailings triggered by an event registration action
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @return	boolean
	 *
	 */
	public function onEventUserRegistered($register_id)
	{	
		//simple, skip if processing not needed
		if (!$this->params->get('reg_mail_user', '1') && !$this->params->get('reg_mail_admin', '0')) {
			return true;
		}

		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();

		$query = ' SELECT a.id, a.title, r.waiting, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug '
				. ' FROM  #__jem_register AS r '
				. ' INNER JOIN #__jem_events AS a ON r.event = a.id '
				. ' WHERE r.id = ' . (int)$register_id;
		$db->setQuery($query);

		if (!$event = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}

		//create link to event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		if ($event->waiting) // registered to the waiting list
		{
			//handle usermail
			if ($this->params->get('reg_mail_user', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_WAITING_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_WAITING_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $this->_receivers;

				$this->_mailer($data);
			}
		} else {
			//handle usermail
			if ($this->params->get('reg_mail_user', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $this->_receivers;

				$this->_mailer($data);
			}
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an attendees being bumped on/off waiting list
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @return	boolean
	 *
	 */
	public function onUserOnOffWaitinglist($register_id)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('reg_mail_user_onoff', '1') && !$this->params->get('reg_mail_admin_onoff', '0')) {
			return true;
		}

		$db 	= JFactory::getDBO();

		$query = ' SELECT a.id, a.title, waiting, uid, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug '
				. ' FROM  #__jem_register AS r '
				. ' INNER JOIN #__jem_events AS a ON r.event = a.id '
				. ' WHERE r.id = ' . (int)$register_id;
		$db->setQuery($query);

		if (!$details = $db->loadObject())
		{
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}

		$user 	= JFactory::getUser($details->uid);
		//create link to event
		$url = JURI::root();
		$link =JRoute::_($url. JEMHelperRoute::getEventRoute($details->slug), false);

		if ($details->waiting) // added to the waiting list
		{
			//handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= array($this->_receivers);

				$this->_mailer($data);
			}
		} else { // bumped from waiting list to attending list
			//handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= $this->_receivers;

				$this->_mailer($data);
			}
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an event unregister action
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @return	boolean
	 *
	 */
	public function onEventUserUnregistered($event_id)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('unreg_mail_user', '1') && !$this->params->get('unreg_mail_admin', '0')) {
			return true;
		}

		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();

		$query = ' SELECT a.id, a.title, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug '
				. ' FROM #__jem_events AS a '
				. ' WHERE a.id = ' . (int)$event_id;
		$db->setQuery($query);

		if (!$event = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}

		//create link to event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		//handle usermail
		if ($this->params->get('unreg_mail_user', '1')) {
			$data 				= new stdClass();
			$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_UNREG_SUBJECT', $this->_SiteName);
			$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_UNREG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
			$data->receivers 	= $user->email;

			$this->_mailer($data);
		}

		//handle adminmail
		if ($this->params->get('unreg_mail_admin', '0') && $this->_receivers) {
			$data 				= new stdClass();
			$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_SUBJECT', $this->_SiteName);
			$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
			$data->receivers 	= $this->_receivers;

			$this->_mailer($data);
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an event store action
	 *
	 * @access  public
	 * @param   int 	$isNew  	 Integer Event identifier
	 * @param   int 	$edited 	 Integer Event new or edited
	 * @return  boolean
	 *
	 */
	public function onEventEdited($event_id, $isNew)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('newevent_mail_user', '1') && !$this->params->get('newevent_mail_admin', '0') &&
		    !$this->params->get('editevent_mail_user', '1') && !$this->params->get('editevent_mail_admin', '0') &&
		    !$this->params->get('editevent_mail_registered', '0') && !$this->params->get('notify_category', '0')) {
			return true;
		}

		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();

		$query = ' SELECT a.id, a.title, a.dates, a.times, CONCAT(a.introtext,a.fulltext) AS text, a.locid, a.published, a.created, a.modified,'
				. ' v.venue, v.city,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
				. ' FROM #__jem_events AS a '
				. ' LEFT JOIN #__jem_venues AS v ON v.id = a.locid'
				. ' WHERE a.id = ' . (int)$event_id;
		$db->setQuery($query);

		if (!$event = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}

		//link for event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		//strip description from tags / scripts, etc...
		$text_description = JFilterOutput::cleanText($event->text);		
		
		//Get IP user		
		if (getenv('HTTP_CLIENT_IP')) {
			$modified_ip =getenv('HTTP_CLIENT_IP');
		} elseif (getenv('HTTP_X_FORWARDED_FOR')) {
		    $modified_ip =getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_X_FORWARDED')) {
			$modified_ip =getenv('HTTP_X_FORWARDED');
		} elseif (getenv('HTTP_FORWARDED_FOR')) {
		    $modified_ip =getenv('HTTP_FORWARDED_FOR');
		} elseif (getenv('HTTP_FORWARDED')) {
			$modified_ip = getenv('HTTP_FORWARDED');
		} else {
		    $modified_ip = $_SERVER['REMOTE_ADDR'];
		}		

		if ($event->published > 0) {
			$adminstate = JText::sprintf('PLG_JEM_MAILER_EVENT_PUBLISHED', $link);
			$userstate = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EVENT_PUBLISHED', $link);
		} else if ($event->published == -2) {
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_TRASHED');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_TRASHED');
		} else {
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_UNPUBLISHED');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_UNPUBLISHED');
		}


		if (($this->params->get('newevent_mail_admin', '0') && $isNew) || ($this->params->get('editevent_mail_admin', '0') && !$isNew)) {
			$query = 'SELECT gm.member'
				. ' FROM #__jem_groupmembers AS gm'
				. ' INNER JOIN #__jem_categories AS c ON c.groupid = gm.group_id'
				. ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = ' . (int)$event_id
				. ' GROUP BY gm.member';
			$db->setQuery($query);

			if (!$group_members_list = $db->loadObjectList()) {
				if ($db->getErrorNum()) {
					JError::raiseWarning('0', $db->getErrorMsg());
					return false;
				}
			}

			$group_mails = array_map(function($member_id) { return JFactory::getUser($member_id->member)->email; }, $group_members_list);
		}

		if ($isNew) {
			if ($this->params->get('newevent_mail_admin', '0') && ($this->_receivers || $group_mails)) {
				$data          = new stdClass();
				$created       = JHtml::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName);
				$data->body    = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT', $user->name, $user->username, $user->email, $event->author_ip, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
				$data->receivers       = array_unique(array_merge($this->_receivers, $group_mails));

				$this->_mailer($data);
			}

			if ($this->params->get('newevent_mail_user', '1')) {
				$data          = new stdClass();
				$created       = JHtml::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body    = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_EVENT', $user->name, $user->username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_NEW_USER_EVENT_MAIL', $this->_SiteName );
				$data->receivers       = $user->email;

				$this->_mailer($data);
			}
		} else {
			if ($this->params->get('editevent_mail_admin', '0') && ($this->_receivers || $group_mails)) {
				$data          = new stdClass();
				$modified      = JHtml::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName);
				$data->body    = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT', $user->name, $user->username, $user->email, $modified_ip, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
				$data->receivers       = array_unique(array_merge($this->_receivers, $group_mails));

				$this->_mailer($data);
			}

			if ($this->params->get('editevent_mail_user', '1')) {
				$data          = new stdClass();
				$modified      = JHtml::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body    = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_EVENT', $user->name, $user->username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_EDIT_USER_EVENT_MAIL', $this->_SiteName );
				$data->receivers       = $user->email;

				$this->_mailer($data);
			}

			if ($this->params->get('editevent_mail_registered', '0')) {
				$query = ' SELECT r.uid'
						. ' FROM #__jem_register AS r'
						. ' WHERE r.event = ' . (int)$event_id;
				$db->setQuery($query);
				if (!$registered_ids = $db->loadObjectList()) {
					if ($db->getErrorNum()) {
						JError::raiseWarning('0', $db->getErrorMsg());
						return false;
					}					
				}
				else
				{
					$data          = new stdClass();
					$modified      = JHtml::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
					$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName);
					$data->body    = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_CAT_NOTIFY', $user->name, $user->username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
					$data->receivers       = array_map(function($obj) { return JFactory::getUser($obj->uid)->email; }, $registered_ids);
	
					$this->_mailer($data);
				}
			}
		}

		if ($this->params->get('notify_category', '0')) {
			$query = ' SELECT c.email'
					. ' FROM #__jem_categories AS c'
					. ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
					. ' WHERE rel.itemid = ' . (int)$event_id;

			$db->setQuery($query);
			if (!$cat_emails_list = $db->loadObjectList()) {
				if ($db->getErrorNum()) {
					JError::raiseWarning('0', $db->getErrorMsg());
				}
				return false;
			}

			$receivers = array();

			foreach ($cat_emails_list as $item) {
				$mails = array_filter(explode(',', trim($item->email)));
				foreach ($mails as $mail) {
					array_push($receivers, $mail);
				}
			}
			$receivers = array_unique($receivers);



			if (!empty($receivers)) {
				$data = new stdClass();
				if ($isNew) {
					$created       = JHtml::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
					$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName);
					$data->body    = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_CAT_NOTIFY', $user->name, $user->username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
				} else {
					$modified      = JHtml::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
					$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName);
					$data->body    = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_CAT_NOTIFY', $user->name, $user->username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
				}
				$data->receivers = $receivers;

				$this->_mailer($data);
			}
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an venue store action
	 *
	 * @access  public
	 * @param   int 	$venue_id 	 Integer Venue identifier
	 * @param   int 	$isNew  	 Integer Venue new or edited
	 * @return  boolean
	 *
	 */
	public function onVenueEdited($venue_id, $isNew)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('newvenue_mail_user', '1') && !$this->params->get('newvenue_mail_admin', '0') &&
		    !$this->params->get('editvenue_mail_user', '1') && !$this->params->get('editvenue_mail_admin', '0')) {
			return true;
		}

		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();

		$query = ' SELECT v.id, v.published, v.venue, v.city, v.street, v.postalCode, v.url, v.country, v.locdescription, v.created, v.modified,'
				. ' CASE WHEN CHAR_LENGTH(v.alias) THEN CONCAT_WS(\':\', v.id, v.alias) ELSE v.id END as slug'
				. ' FROM #__jem_venues AS v'
				. ' WHERE v.id = ' . (int)$venue_id;
		$db->setQuery($query);

		if (!$venue = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}

		//link for venue
		$link = JRoute::_(JURI::base().JEMHelperRoute::getVenueRoute($venue->slug), false);

		//strip description from tags / scripts, etc...
		$text_description = JFilterOutput::cleanText($venue->locdescription);

		$modified_ip 	= getenv('REMOTE_ADDR');
		//$edited 		= JHtml::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );

		$adminstate = $venue->published ? JText::sprintf('PLG_JEM_MAILER_VENUE_PUBLISHED', $link) : JText::_('PLG_JEM_MAILER_VENUE_UNPUBLISHED');
		$userstate = $venue->published ? JText::sprintf('PLG_JEM_MAILER_USER_MAIL_VENUE_PUBLISHED', $link) : JText::_('PLG_JEM_MAILER_USER_MAIL_VENUE_UNPUBLISHED');

		if ($isNew) {
			if ($this->params->get('newvenue_mail_admin', '0') && $this->_receivers) {
				$data                  = new stdClass();
				$created       = JHtml::Date( $venue->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_VENUE_MAIL', $this->_SiteName);
				$data->body    = JText::sprintf('PLG_JEM_MAILER_NEW_VENUE', $user->name, $user->username, $user->email, $venue->author_ip, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
				$data->receivers       = $this->_receivers;

				$this->_mailer($data);
			}

			if ($this->params->get('newvenue_mail_user', '1')) {
				$data                  = new stdClass();
				$created       = JHtml::Date( $venue->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body    = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_VENUE', $user->name, $user->username, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_NEW_USER_VENUE_MAIL', $this->_SiteName );
				$data->receivers       = $user->email;

				$this->_mailer($data);
			}
		} else {
			if ($this->params->get('editvenue_mail_admin', '0') && $this->_receivers) {
				$data                  = new stdClass();
				$modified      = JHtml::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_VENUE_MAIL', $this->_SiteName);
				$data->body    = JText::sprintf('PLG_JEM_MAILER_EDIT_VENUE', $user->name, $user->username, $user->email, $modified_ip, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
				$data->receivers       = $this->_receivers;

				$this->_mailer($data);
			}

			if ($this->params->get('editvenue_mail_user', '1')) {
				$data                  = new stdClass();
				$modified      = JHtml::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body    = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_VENUE', $user->name, $user->username, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_EDIT_USER_VENUE_MAIL', $this->_SiteName );
				$data->receivers       = $user->email;

				$this->_mailer($data);
			}
		}

		return true;
	}

	/**
	 * This method executes and send the mail
	 *
	 * @access	private
	 * @param   object 	$data 	 mail data object
	 * @return	boolean
	 *
	 */
	private function _mailer($data)
	{
		$receivers = is_array($data->receivers) ? $data->receivers : array($data->receivers);

		foreach ($receivers as $receiver) {
			$mail = JFactory::getMailer();

			$mail->setSender( array( $this->_MailFrom, $this->_FromName ) );
			$mail->setSubject( $data->subject );
			$mail->setBody( $data->body );

			$mail->addRecipient($receiver);
			$mail->send();
		}

		return true;
	}
}
?>
