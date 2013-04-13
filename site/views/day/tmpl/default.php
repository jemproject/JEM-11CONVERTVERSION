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
?>
<div id="jem" class="el_jem">
<p class="buttons">
	<?php
		echo ELOutput::printbutton( $this->print_link, $this->params );
	?>
</p>

<h1 class="componentheading">
	<?php echo $this->daydate; ?>
</h1>

<!--table-->

<form action="<?php echo JRoute::_('index.php') ?>" method="post" id="adminForm">
<?php echo $this->loadTemplate('table'); ?>

<p>
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
</p>
</form>

<!--footer-->

<?php if (( $this->page > 0 ) && ( !$this->params->get( 'popup' ) )) : ?>
<div class="pageslinks">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>

<p class="pagescounter">
	<?php echo $this->pagination->getPagesCounter(); ?>
</p>
<?php endif; ?>

<p class="copyright">
	<?php echo ELOutput::footer( ); ?>
</p>

</div>