<?php
/**
 * @version development 
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

if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR); 
defined('_JEXEC') or die;

/**
 * Holds the logic for attachments manipulation
 *
 * @package JEM
 */
class ELAttach extends JObject {

	/**
	 * upload files for the specified object
	 * 
	 * @param array data from JRequest 'files'
	 * @param string object identification (should be event<eventid>, category<categoryid>, etc...)
	 */
static	function postUpload($post_files, $object)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$params = JComponentHelper::getParams('com_jem');
		$elsettings =  ELHelper::config();

		$path = JPATH_SITE.DS.$elsettings->attachments_path.DS.$object;
		
		if (!(is_array($post_files) && count($post_files))) {
			return false;
		}
		
		$allowed = explode(",", $elsettings->attachments_types);
		foreach ($allowed as $k => $v) {
			$allowed[$k] = trim($v);
		}
		
		$maxsizeinput = $elsettings->attachments_maxsize;
		$defaultsize = 1000;
		
		
		$maxsize = $maxsizeinput * $defaultsize;
		foreach ($post_files['name'] as $k => $file)
		{
			if (empty($file)) {
				continue;
			}
			// check extension
			
			$tmp = explode(".", strtolower($file));
			if (!in_array(end($tmp), $allowed)) {
				JError::raiseWarning(0, JText::_('COM_JEM_ERROR_ATTACHEMENT_EXTENSION_NOT_ALLOWED').': '.$file);
				continue;
			}
			// check size
			if ($post_files['size'][$k] > $maxsize) {
				JError::raiseWarning(0, JText::sprintf('COM_JEM_ERROR_ATTACHEMENT_FILE_TOO_BIG', $file, $post_files['size'][$k], $maxsize));
				continue;
			}
			
			if (!JFolder::exists($path)) 
			{
				// try to create it
				$res = JFolder::create($path);
				if (!$res) {
					JError::raiseWarning(0, JText::_('COM_JEM_ERROR_COULD_NOT_CREATE_FOLDER').': '.$path);
					return false;
				}
				$text = '<html><body bgcolor="#FFFFFF"></body></html>';
				JFile::write($path.DS.'index.html', $text);
			}
			
			JFile::copy($post_files['tmp_name'][$k], $path.DS.$file);
			
			$table = JTable::getInstance('jem_attachments', '');
			$table->file = $file;
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
				JError::raiseWarning(0, JText::_('COM_JEM_ATTACHMENT_ERROR_SAVING_TO_DB').': '.$table->getError());				
			}
		}
		return true;
	}
	
	/**
	 * update attachment record in db
	 * @param array (id, name, description, access)
	 */
static	function update($attach)
	{
		if (!is_array($attach) || !isset($attach['id']) || !(intval($attach['id']))) {
			return false;
		}
		$table = JTable::getInstance('jem_attachments', '');
		$table->load($attach['id']);
		$table->bind($attach);
		if (!($table->check() && $table->store())) {
			JError::raiseWarning(0, JText::_('COM_JEM_ATTACHMENT_ERROR_UPDATING_RECORD').': '.$table->getError());		
			return false;		
		}		
		return true;
	}
	
	/**
	 * return attachments for objects
	 * @param string object identification (should be event<eventid>, category<categoryid>, etc...)
	 * @return array
	 */
static	function getAttachments($object)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$app = JFactory::getApplication();
		$params = JComponentHelper::getParams('com_jem');
		$elsettings =  ELHelper::config();
		
		$user	=  JFactory::getUser();
			
		  if (JFactory::getUser()->authorise('core.manage')) {
              $gid = (int) 3;          //viewlevel Special
          } else {
              if($user->get('id')) {
                  $gid = (int) 2;     //viewlevel Registered
              } else {
                 $gid = (int) 1;      //viewlevel Public
              }
                 }


		$path = JPATH_SITE.DS.$elsettings->attachments_path.DS.$object;
		
		if (!file_exists($path)) {
			return array();
		}
		// first list files in the folder
		$files = JFolder::files($path, null, false, false, array('index.html'));

		// then get info for files from db
		$db = JFactory::getDBO();
		$fnames = array();
		foreach ($files as $f) {
			$fnames[] = $db->Quote($f);
		}
		if (!count($fnames)) {
			return array();
		}
				
		$query = ' SELECT * ' 
		       . ' FROM #__jem_attachments ' 
		       . ' WHERE file IN ('. implode(',', $fnames) .')'
		       . '   AND object = '. $db->Quote($object);
		if (!is_null($gid)) {
			$query .= ' AND access <= '.$db->Quote($gid);
		}
		$query .= ' ORDER BY ordering ASC ';
		
		$db->setQuery($query);
		$res = $db->loadObjectList();
		
		return $res;
	}
	
	/**
	 * get the file
	 * 
	 * @param int $id
	 */
    static	function getAttachmentPath($id) 
	{		
		$params = JComponentHelper::getParams('com_jem');
		$elsettings =  ELHelper::config();
		
		$user	=  JFactory::getUser();
			
		  if (JFactory::getUser()->authorise('core.manage')) {
              $gid = (int) 3;          //viewlevel Special
          } else {
              if($user->get('id')) {
                  $gid = (int) 2;     //viewlevel Registered
              } else {
                 $gid = (int) 1;      //viewlevel Public
              }
}
		
		
		$db = JFactory::getDBO();
		$query = ' SELECT * ' 
		       . ' FROM #__jem_attachments ' 
		       . ' WHERE id = '. $db->Quote(intval($id));
		$db->setQuery($query);
		$res = $db->loadObject();
		if (!$res) {
			JError::raiseError(404, JText::_('COM_JEM_FILE_UNKNOWN'));
		}		
		
		if (!is_null($gid) && $res->access > $gid) {
			JError::raiseError(403, JText::_('COM_JEM_YOU_DONT_HAVE_ACCESS_TO_THIS_FILE'));			
		}
		
		$path = JPATH_SITE.DS.$elsettings->attachments_path.DS.$res->object.DS.$res->file;		
		if (!file_exists($path)) {
			JError::raiseError(404, JText::_('COM_JEM_FILE_NOT_FOUND'));
		}
		
		return $path;
	}
	
	
	/**
	 * remove attachment for objects
	 * 
	 * @param id from db
	 * @param string object identification (should be event<eventid>, category<categoryid>, etc...)
	 * @return boolean
	 */
    static	function remove($id)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$app = JFactory::getApplication();
		$params = JComponentHelper::getParams('com_jem');
		$elsettings =  ELHelper::config();
		
		// then get info for files from db
		$db = JFactory::getDBO();
		
		$query = ' SELECT file, object ' 
		       . ' FROM #__jem_attachments ' 
		       . ' WHERE id = ' . $db->Quote($id);
		$db->setQuery($query);
		$res = $db->loadObject();
		if (!$res)
		{
			return false;
		}
				
		$path = JPATH_SITE.DS.$elsettings->attachments_path.DS.$res->object.DS.$res->file;
		if (file_exists($path)) {
			JFile::delete($path);
		}
				
		$query = ' DELETE FROM #__jem_attachments ' 
		       . ' WHERE id = '. $db->Quote($id);
		$db->setQuery($query);
		$res = $db->query();
		if (!$res) {
			return false;
		}
		
		return true;
	}
}