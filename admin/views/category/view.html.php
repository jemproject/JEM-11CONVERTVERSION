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

defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the JEM category screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewCategory extends JViewLegacy {

	function display($tpl = null)
	{
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$editor 	=  JFactory::getEditor();
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();
		$app 		=  JFactory::getApplication();

		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');

		//get vars
		$cid 		= JRequest::getVar( 'cid' );

		//add css to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');
		$document->addScript(JURI::root().'media/com_jem/js/attachments.js' );
		// for color picker
    	$document->addStyleSheet(JURI::root().'media/com_jem/css/picker.css');
    	$document->addScript( JURI::root().'media/com_jem/js/picker.js' );

		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'COM_JEM_EDIT_CATEGORY' ), 'categoriesedit' );

		} else {
			JToolBarHelper::title( JText::_( 'COM_JEM_ADD_CATEGORY' ), 'categoriesedit' );

			//set the submenu
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_JEM' ), 'index.php?option=com_jem');
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_EVENTS' ), 'index.php?option=com_jem&view=events');
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_VENUES' ), 'index.php?option=com_jem&view=venues');
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_CATEGORIES' ), 'index.php?option=com_jem&view=categories');
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'index.php?option=com_jem&view=archive');
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_GROUPS' ), 'index.php?option=com_jem&view=groups');
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_HELP' ), 'index.php?option=com_jem&view=help');
			if ($user->get('gid') > 24) {
				JSubMenuHelper::addEntry( JText::_( 'COM_JEM_SETTINGS' ), 'index.php?option=com_jem&controller=settings&task=edit');
			}
		}
		JToolBarHelper::apply();
		JToolBarHelper::spacer();
		JToolBarHelper::save();
		JToolBarHelper::spacer();
		//JToolBarHelper::media_manager();
		//JToolBarHelper::spacer();
		JToolBarHelper::cancel();
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'el.editcategories', true );

		//Get data from the model
		$model		=  $this->getModel();
		$row     	=  $this->get( 'Data' );
		$groups 	=  $this->get( 'Groups' );
		$categories = JEMCategories::getCategoriesTree(0);

		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->catname.' '.JText::_( 'COM_JEM_EDITED_BY_ANOTHER_ADMIN' ));
				$app->redirect( 'index.php?option=com_jem&view=categories' );
			}
		}

		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, 'catdescription' );

		//build selectlists
		$Lists = array();
		$javascript = "onchange=\"javascript:if (document.forms[0].image.options[selectedIndex].value!='') {document.imagelib.src='../images/stories/' + document.forms[0].image.options[selectedIndex].value} else {document.imagelib.src='../images/blank.png'}\"";
	//	$Lists['imagelist'] 		= JHTML::_('list.images', 'image', $row->image, $javascript, '/images/stories/' );
	//	$Lists['access'] 			= JHTML::_('list.accesslevel', $row );
	//  $Lists['access'] = JHTML::_('access.level', 'access', $row->access); 
	$Lists['access'] 			= JHTML::_('access.assetgrouplist', 'access', $row->access);
	
	
		$Lists['parent_id'] 		= JEMCategories::buildcatselect($categories, 'parent_id', $row->parent_id, 1);


		//build image select js and load the view
		$js = "
		function elSelectImage(image, imagename) {
			document.getElementById('a_image').value = image;
			document.getElementById('a_imagename').value = imagename;
			document.getElementById('imagelib').src = '../images/jem/categories/' + image;
			var btn = window.parent.document.getElementById('sbox-btn-close');
            btn.fireEvent('click');
		}";
		
		$link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task=categoriesimg&amp;tmpl=component';
		$link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task=selectcategoriesimg&amp;tmpl=component';
		$document->addScriptDeclaration($js);
		$imageselect = "\n<input style=\"background: #ffffff;\" type=\"text\" id=\"a_imagename\" value=\"$row->image\" disabled=\"disabled\" onchange=\"javascript:if (document.forms[0].a_imagename.value!='') {document.imagelib.src='../images/jem/categories/' + document.forms[0].a_imagename.value} else {document.imagelib.src='../images/blank.png'}\"; /><br />";

		$imageselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_UPLOAD')."\" href=\"$link\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_UPLOAD')."</a></div></div>\n";
		$imageselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECTIMAGE')."\" href=\"$link2\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECTIMAGE')."</a></div></div>\n";

		$imageselect .= "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"elSelectImage('', '".JText::_('COM_JEM_SELECTIMAGE')."' );\" value=\"".JText::_('COM_JEM_RESET')."\" />";
		$imageselect .= "\n<input type=\"hidden\" id=\"a_image\" name=\"image\" value=\"$row->image\" />";
		
		$this->imageselect 	= $imageselect;
		

		//build grouplist
		$grouplist		= array();
		$grouplist[] 	= JHTML::_('select.option', '0', JText::_( 'COM_JEM_NO_GROUP' ) );
		$grouplist 		= array_merge( $grouplist, $groups );

		$Lists['groups']	= JHTML::_('select.genericlist', $grouplist, 'groupid', 'size="1" class="inputbox"', 'value', 'text', $row->groupid );

		//assign data to template
		$this->Lists 	= $Lists;
		$this->row 		= $row;
		$this->editor 	= $editor;
		$access2 = ELHelper::getAccesslevelOptions();
		$this->access 	= $access2;

		parent::display($tpl);
	}
}
?>