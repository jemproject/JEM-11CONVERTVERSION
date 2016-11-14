<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

// ensure JemFactory is loaded (because this class is used by modules or plugins too)
require_once(JPATH_SITE . '/components/com_jem/factory.php');

/**
 * Holds the logic for attachments manipulation
 *
 * @package JEM
 */
class JemAttachment extends JObject
{
	/**
	 * upload files for the specified object
	 *
	 * @param array  $post_files from JInput 'files'
	 * @param string $object     identification (should be event<eventid>, category<categoryid>, etc...)
	 *
	 * @since 1.1
	 * @return bool
	 */
	public static function postUpload($post_files, $object)
	{
		require_once JPATH_SITE . '/com_jem/classes/image.class.php'; // TODO: JPATH_COMPONENT

		$user = JemFactory::getUser();
		$jemsettings = JemHelper::config();

		$path = JPATH_SITE . '/' . $jemsettings->attachments_path . '/' . $object;

		if (!(is_array($post_files) && count($post_files))) {
			return false;
		}

		$allowed = explode(",", $jemsettings->attachments_types);
		foreach ($allowed as $k => $v) {
			$allowed[$k] = trim($v);
		}

		$maxsizeinput = $jemsettings->attachments_maxsize*1024; //size in kb

		foreach ($post_files['name'] as $k => $file)
		{
			if (empty($file)) {
				continue;
			}

			// check if the filetype is valid
			$fileext = strtolower(JFile::getExt($file));
			if (!in_array($fileext, $allowed)) {
				JError::raiseWarning(0, JText::_('COM_JEM_ERROR_ATTACHEMENT_EXTENSION_NOT_ALLOWED') . ': ' . $file);
				continue;
			}
			// check size
			if ($post_files['size'][$k] > $maxsizeinput) {
				JError::raiseWarning(0, JText::sprintf('COM_JEM_ERROR_ATTACHEMENT_FILE_TOO_BIG', $file, $post_files['size'][$k], $maxsizeinput));
				continue;
			}

			if (!JFolder::exists($path)) {
				// try to create it
				$res = JFolder::create($path);
				if (!$res) {
					JError::raiseWarning(0, JText::_('COM_JEM_ERROR_COULD_NOT_CREATE_FOLDER') . ': ' . $path);
					return false;
				}
			}

			// TODO: Probably move this to a helper class

			$sanitizedFilename = JemImage::sanitize($path, $file);

			// Make sure that the full file path is safe.
			$filepath = JPath::clean($path . '/' . $sanitizedFilename);
			// Since Joomla! 3.4.0 JFile::upload has some more params to control new security parsing
			// Unfortunately this parsing is partially stupid so it may reject archives for non-understandable reason.
			if (version_compare(JVERSION, '3.4', 'lt')) {
				JFile::upload($post_files['tmp_name'][$k], $filepath);
			} else {
				// switch off parsing archives for byte sequences looking like a script file extension
				// but keep all other checks running
				JFile::upload($post_files['tmp_name'][$k], $filepath, false, false, array('fobidden_ext_in_content' => false));
			}

			$table = JTable::getInstance('jem_attachments', '');
			$table->file = $sanitizedFilename;
			$table->object = $object;
			if (isset($post_files['customname'][$k]) && !empty($post_files['customname'][$k])) {
				$table->name = $post_files['customname'][$k];
			}
			if (isset($post_files['description'][$k]) && !empty($post_files['description'][$k])) {
				$table->description = $post_files['description'][$k];
			}
			if (isset($post_files['access'][$k])) {
				$table->access = intval($post_files['access'][$k]);
			}
			$table->added = strftime('%F %T');
			$table->added_by = $user->get('id');

			if (!($table->check() && $table->store())) {
				JError::raiseWarning(0, JText::_('COM_JEM_ATTACHMENT_ERROR_SAVING_TO_DB') . ': ' . $table->getError());
			}
		}

		return true;
	}

	/**
	 * update attachment record in db
	 *
	 * @param array $attach (id, name, description, access)
	 *
	 * @since 1.1
	 * @return bool
	 */
	public static function update($attach)
	{
		if (!is_array($attach) || !isset($attach['id']) || !(intval($attach['id']))) {
			return false;
		}

		$table = JTable::getInstance('jem_attachments', '');
		$table->load($attach['id']);
		$table->bind($attach);

		if (!($table->check() && $table->store())) {
			JError::raiseWarning(0, JText::_('COM_JEM_ATTACHMENT_ERROR_UPDATING_RECORD') . ': ' . $table->getError());
			return false;
		}

		return true;
	}

	/**
	 * return attachments for objects
	 * @param string $object identification (should be event<eventid>, category<categoryid>, etc...)
	 *
	 * @since 1.1
	 * @return array
	 */
	public static function getAttachments($object)
	{
		$jemsettings = JemHelper::config();

		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$path = JPATH_SITE . '/' . $jemsettings->attachments_path . '/' . $object;

		if (!file_exists($path)) {
			return array();
		}

		// first list files in the folder
		$files = JFolder::files($path, null, false, false);

		// then get info for files from db
		$db = JFactory::getDbo();
		$fnames = array();
		foreach ($files as $f) {
			$fnames[] = $db->quote($f);
		}

		if (!count($fnames)) {
			return array();
		}

		$query = ' SELECT * '
			   . ' FROM #__jem_attachments '
			   . ' WHERE file IN ('. implode(',', $fnames) .')'
			   . '   AND object = '. $db->quote($object);
		$query .= ' AND access IN (' . implode(',', $levels) . ')';
		$query .= ' ORDER BY ordering ASC ';

		$db->setQuery($query);
		$res = $db->loadObjectList();

		return $res;
	}

	/**
	 * get the file
	 *
	 * @param int $id
	 * @since 1.1
	 * @return string
	 */
	public static function getAttachmentPath($id)
	{
		$jemsettings = JemHelper::config();

		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$db = JFactory::getDbo();
		$query = ' SELECT * '
			   . ' FROM #__jem_attachments '
			   . ' WHERE id = '. $db->quote(intval($id));
		$db->setQuery($query);
		$res = $db->loadObject();
		if (!$res) {
			JError::raiseError(404, JText::_('COM_JEM_FILE_UNKNOWN'));
		}

		if (!in_array($res->access, $levels)) {
			JError::raiseError(403, JText::_('COM_JEM_YOU_DONT_HAVE_ACCESS_TO_THIS_FILE'));
		}

		$path = JPATH_SITE . '/' . $jemsettings->attachments_path . '/' . $res->object . '/' . $res->file;
		if (!file_exists($path)) {
			JError::raiseError(404, JText::_('COM_JEM_FILE_NOT_FOUND'));
		}

		return $path;
	}

	/**
	 * remove attachment for objects
	 *
	 * @param int $id from db
	 * @param string object identification (should be event<eventid>, category<categoryid>, etc...)
     * @since 1.1
	 * @return boolean
	 */
	public static function remove($id)
	{
		$jemsettings = JemHelper::config();

		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();
		$userid = $user->get('id');

		// then get info for files from db
		$db = JFactory::getDbo();

		$query = ' SELECT file, object, added_by '
			   . ' FROM #__jem_attachments '
			   . ' WHERE id = ' . $db->quote($id) . ' AND access IN (0,' . implode(',', $levels) . ')';
		$db->setQuery($query);
		$res = $db->loadObject();

		if (!$res) {
			return false;
		}

		// check permission
		if (empty($userid) || ($userid != $res->added_by)) {
			if (strncasecmp($res->object, 'event', 5) == 0) {
				$type = 'event';
				$itemid = (int)substr($res->object, 5);
				$table = '#__jem_events';
			} elseif (strncasecmp($res->object, 'venue', 5) == 0) {
				$type = 'venue';
				$itemid = (int)substr($res->object, 5);
				$table = '#__jem_venues';
			} else {
				return false;
			}
			// get item owner
			$query = ' SELECT created_by FROM ' . $table . ' WHERE id = ' . $db->quote($itemid);
			$db->setQuery($query);
			$created_by = $db->loadResult();

			if (!$user->can('edit', $type, $itemid, $created_by)) {
				JemHelper::addLogEntry("User ${userid} is not permitted to remove attachment " . $res->object, __METHOD__);
				return false;
			}
		}

		JemHelper::addLogEntry("User ${userid} removes attachment " . $res->object . '/' . $res->file, __METHOD__);
		$path = JPATH_SITE . '/' . $jemsettings->attachments_path . '/' . $res->object . '/' . $res->file;
		if (file_exists($path)) {
			JFile::delete($path);
		}

		$query = ' DELETE FROM #__jem_attachments '
			   . ' WHERE id = '. $db->quote($id);
		$db->setQuery($query);
		$res = $db->execute();
		if (!$res) {
			return false;
		}

		return true;
	}
}