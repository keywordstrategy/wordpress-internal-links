<div class="wrap">
<h2 style="float:left;">Keyword Strategy</h2>

<h2 class="nav-tab-wrapper">
<a class="nav-tab " href="<?= KWS_PLUGIN_URL ?>">Overview</a>
	<a class="nav-tab nav-tab-active" href="<?= KWS_PLUGIN_URL . '&kws_action=inpage' ?>">Insert Keywords</a>
	<a class="nav-tab" href="<?= KWS_PLUGIN_URL . '&kws_action=related' ?>">Links Needed</a>
</h2>

<p>
	These are keyword variations that you could enter into your pages. Click "Edit Page" to open the article and find a good place to put the text in. <br /> "Blacklist" will blacklist the keyword in your Keyword Strategy project. Click "Detach from URL" if you don't want to associate this keyword with this page.
</p>
<p>Last update: <?= $kws_options['last_update']? date('Y-m-d H:i', $kws_options['last_update']).", {$inpage_total_keywords} keywords" : 'Never'?> <span><input class="button" type="submit" value="Update now" onclick="window.location = '<?= KWS_PLUGIN_URL ?>' + '&kws_action=update_now_inpage'; this.parentNode.innerHTML = 'Updating... Please wait...'" /></span></p>
<p> Your keywords will update automatically every day.

	<form action="<?= KWS_PLUGIN_URL ?>" method="get" style="text-align:right;">
		<input type="hidden" name="page" value="keyword-strategy-internal-links" />
		<input type="hidden" name="kws_action" value="inpage" />
		<? if ($_REQUEST['search']): ?>
			<input type="button" onclick="this.parentNode.search.value = ''; this.parentNode.submit();" class="button" value="Reset '<?= htmlspecialchars(stripslashes($_REQUEST['search'])) ?>' search" />
		<? endif; ?>
		<input type="text" name="search" value="<?= htmlspecialchars(stripslashes($_REQUEST['search'])) ?>" />
		<input class="button" type="submit" value="Find" />
	</form>

<? if ($inpage): ?>
<form action="<?= KWS_PLUGIN_URL ?>" method="post">
<div class="tablenav">
<div class="alignleft actions">
	<input type="hidden" name="kws_action" value="inpage_form" />
	<select name="inpage_action" style="margin: 10px 0; width:130px;">
		<option value="none">Bulk Actions</option>
		<option value="blacklist">Blacklist</option>
		<option value="detach">Detach</option>
	</select>
	<input type="submit" value="Apply" class="button-secondary action" />
</div>
<div class="alignright actions">
	<?= kws_pagination('top', $page_args) ?>
</div>
</div>
<table class="wp-list-table widefat fixed pages kws-table" cellspacing="0">
	<thead>
		<tr>
			<th scope="col" class="manage-column column-cb check-column" style=""><input type="checkbox"></th>
			<th scope="col" class="manage-column sorted" kws-column="keyword" style=""><a href="#"><span>Keyword</span></a></th>
			<th scope="col" class="manage-column sorted" kws-column="url" style=""><span>URL</span></th>
		</tr>
	</thead>
	
	<tfoot>
	<tr>
		<th scope="col" class="manage-column column-cb check-column"><input type="checkbox"></th>
		<th scope="col" class="manage-column"><span>Keyword</span></th>
		<th scope="col" class="manage-column"><span>URL</span></th>	</tr>
	</tfoot>

	<tbody>
	<? foreach ($inpage AS $item): ?>
			<tr class="alternate author-self status-publish format-default iedit" valign="top">
			<th scope="row" class="check-column"><input type="checkbox" name="keyword[]" value="<?= $item['id'] ?>" /></th>
				<td class="post-title page-title column-title"><strong><?= htmlspecialchars($item['keyword']) ?></strong>


<div class="row-actions">
<span class="edit"><a target="_blank" href="post.php?post=<?= $item['post_id'] ?>&amp;action=edit" title="Edit this page">Edit Page</a> |</span>
	<span class="trash"><a class="submitdelete" title="Blacklist keyword" href="<?= KWS_PLUGIN_URL ?>&kws_action=inpage_form&inpage_action=blacklist&keyword[]=<?= $item['id'] ?>">Blacklist Keyword</a> |
	</span>
	<span class="trash"><a href="<?= KWS_PLUGIN_URL ?>&kws_action=inpage_form&inpage_action=detach&keyword[]=<?= $item['id'] ?>" title="Detach keyword from this URL" rel="permalink">Detach from this URL</a></span>
</div>
</td>			
<td class=""><a target="_blank" href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['url']) ?></a></td>
						
					</tr>
	<? endforeach; ?>
		</tbody>
</table>
<div class="tablenav">
	<div class="alignleft actions">
		<select name="inpage_action2" style="margin-top: 10px; width:130px;">
			<option value="none">Bulk Actions</option>
			<option value="blacklist">Blacklist</option>
			<option value="detach">Detach</option>
		</select>
		<input type="submit" value="Apply" name="apply2" class="button-secondary action" />
	</div>
	<div class="alignright actions">
		<?= kws_pagination('bottom', $page_args) ?>
	</div>
</div>

</form>
</div>
<? else: ?>

<p>
	<? if ($_REQUEST['search']): ?>
	--- No keywords found ---
	<? else: ?>
	--- No keywords available ---
	<? endif; ?>
</p>

<? endif; ?>
</div>

<script>
(function($){
	var sort_column = '<?= $_REQUEST['sort'] ?>';
	var sort_dir = '<?= $_REQUEST['dir'] ?>';
	$(document).ready(function(){
		$('.kws-table th.sorted[kws-column]').each(function(){
			var th = $(this);
			var sorted = false;
			var column = th.attr('kws-column');
			var text = th.text();
			if (sort_column == column) {
				sorted = true;
				th.addClass(sort_dir);
			}
			th.html('<a href="#"><span>'+text+'</span><span class="sorting-indicator"></span></a>');
			var a = th.find('a');
			a.attr('href', location.href.replace(/&sort=[^&]+/g, '').replace(/&dir=[^&]+/g, '') + '&sort='+column+'&dir='+(sorted && sort_dir=='asc'? 'desc': 'asc'))
			a.mouseover(function(){
				if (sorted) {
					th.addClass(sort_dir == 'asc'? 'asc' : 'desc');
					th.removeClass(sort_dir == 'asc'? 'desc' : 'asc');
				}
				else {
					th.addClass('desc');
				}
			});
			a.mouseout(function(){
				if (sorted) {
					th.removeClass(sort_dir == 'asc'? 'asc' : 'desc');
					th.addClass(sort_dir);
				}
				else {
					th.removeClass('desc');
					th.removeClass('asc');
				}
			});
		});
	});
})(jQuery);
</script>
